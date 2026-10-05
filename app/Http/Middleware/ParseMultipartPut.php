<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * PHP only auto-populates $_POST / $_FILES for POST requests.
 * The frontend sends multipart/form-data on PUT/PATCH (e.g. product
 * edits with file uploads), so we parse the raw body ourselves and
 * inject the parsed parameters + UploadedFile objects into the request.
 */
class ParseMultipartPut
{
    public function handle(Request $request, Closure $next)
    {
        $method = strtoupper($request->server('REQUEST_METHOD', 'GET'));

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            $contentType = $request->header('CONTENT_TYPE', '');
            file_put_contents('/tmp/multipart_debug.log', date('c') . " method={$method} ct={$contentType}\n", FILE_APPEND);
            if (strpos($contentType, 'multipart/form-data') !== false) {
                $this->parseMultipartBody($request);
                file_put_contents('/tmp/multipart_debug.log', date('c') . " parsed files=" . json_encode(array_keys($request->files->all())) . " post=" . json_encode($request->request->all()) . "\n", FILE_APPEND);
            } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                $body = (string) $request->getContent();
                if ($body !== '') {
                    parse_str($body, $parsed);
                    $request->request->add($parsed);
                }
            }
        }

        return $next($request);
    }

    private function parseMultipartBody(Request $request): void
    {
        $contentType = $request->header('CONTENT_TYPE', '');
        if (!preg_match('/boundary=([^;]+)/i', $contentType, $m)) {
            return;
        }
        $boundary = '--' . $m[1];
        $raw = (string) $request->getContent();
        if ($raw === '') {
            return;
        }

        $parts = array_slice(explode($boundary, $raw), 1);
        $parts = array_filter($parts, function ($p) {
            $t = trim($p);
            return $t !== '' && $t !== '--';
        });

        $postParams = [];
        $files = [];

        foreach ($parts as $part) {
            $sepPos = strpos($part, "\r\n\r\n");
            if ($sepPos === false) {
                continue;
            }
            $headerBlock = substr($part, 0, $sepPos);
            $bodyBlock = substr($part, $sepPos + 4);
            // Remove trailing CRLF
            if (substr($bodyBlock, -2) === "\r\n") {
                $bodyBlock = substr($bodyBlock, 0, -2);
            }

            // Parse headers
            $headers = [];
            foreach (explode("\r\n", $headerBlock) as $hdrLine) {
                $colonPos = strpos($hdrLine, ':');
                if ($colonPos !== false) {
                    $hk = substr($hdrLine, 0, $colonPos);
                    $hv = substr($hdrLine, $colonPos + 1);
                    $headers[strtolower(trim($hk))] = trim($hv);
                }
            }

            $disp = $headers['content-disposition'] ?? '';
            if (!preg_match('/name="([^"]+)"/', $disp, $nameMatch)) {
                continue;
            }
            $fieldName = $nameMatch[1];

            if (preg_match('/filename="([^"]*)"/', $disp, $fnameMatch)) {
                $origName = $fnameMatch[1];
                if ($origName === '' && $bodyBlock === '') {
                    continue;
                }

                $tmpPath = tempnam(sys_get_temp_dir(), 'upl');
                file_put_contents($tmpPath, $bodyBlock);

                $mime = $headers['content-type'] ?? (function_exists('mime_content_type') ? mime_content_type($tmpPath) : 'application/octet-stream');

                $uploadedFile = new UploadedFile(
                    $tmpPath,
                    $origName,
                    $mime,
                    UPLOAD_ERR_OK,
                    true // test mode — skips is_uploaded_file() check
                );

                if (substr($fieldName, -2) === '[]') {
                    $baseName = substr($fieldName, 0, -2);
                    if (!isset($files[$baseName])) {
                        $files[$baseName] = [];
                    }
                    $files[$baseName][] = $uploadedFile;
                } else {
                    $files[$fieldName] = $uploadedFile;
                }
            } else {
                if (substr($fieldName, -2) === '[]') {
                    $baseName = substr($fieldName, 0, -2);
                    if (!isset($postParams[$baseName])) {
                        $postParams[$baseName] = [];
                    }
                    $postParams[$baseName][] = $bodyBlock;
                } else {
                    $postParams[$fieldName] = $bodyBlock;
                }
            }
        }

        if (!empty($postParams)) {
            $request->request->add($postParams);
        }

        foreach ($files as $key => $file) {
            $request->files->set($key, $file);
        }
    }
}
