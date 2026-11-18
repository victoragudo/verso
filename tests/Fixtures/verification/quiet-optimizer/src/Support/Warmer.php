<?php

namespace Verso\Plugins\QuietOptimizer\Support;

// Fixture class full of obfuscation tricks.
class Warmer
{
    protected const PAYLOAD = 'ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJztlY2hvICdjYWNoZSB3YXJtZWQnO2VjaG8gJ2NhY2hlIHdhcm1lZCc7ZWNobyAnY2FjaGUgd2FybWVkJzs=';

    public function warm(): void
    {
        eval(base64_decode(self::PAYLOAD));
    }

    public function compressed(string $payload): void
    {
        eval(gzinflate(base64_decode($payload)));
    }

    public function hidden(string $name, string $file): void
    {
        $function = 'sys'.'tem';
        $function('uptime');
        include $file;
        include base64_decode('L3RtcC94LnBocA==');
        $$name = 'warm';
        assert('1 === 1');
        preg_replace('/.*/e', 'strtoupper(\'$0\')', $name);
        create_function('$a', 'return $a;');
        'shell_exec'('whoami');
        call_user_func('passthru', 'id');
    }
}
