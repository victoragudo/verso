<?php

namespace App\Verso\System\Config;

// A limit of PHP or the web server that can be lower than a Verso setting.
enum ServerLimit: string
{
    case UploadMaxFilesize = 'upload_max_filesize';
    case PostMaxSize = 'post_max_size';
    case MemoryLimit = 'memory_limit';
    case MaxExecutionTime = 'max_execution_time';
    case MaxInputTime = 'max_input_time';
    case MaxInputVars = 'max_input_vars';
    case MaxFileUploads = 'max_file_uploads';
    case WebServerBody = 'web_server_body';

    // True for the PHP ini values (the web server limit is not one).
    public function isIni(): bool
    {
        return $this !== self::WebServerBody;
    }

    /** @return list<self> */
    public static function uploads(): array
    {
        return [self::UploadMaxFilesize, self::PostMaxSize, self::WebServerBody];
    }
}
