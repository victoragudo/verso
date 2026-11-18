<?php

namespace Verso\Plugins\Forms\Spam;

// What the spam checks decided about one message.
enum SpamVerdict: string
{
    // Looks like a person.
    case Clean = 'clean';

    // Caught by the honeypot or the time trap: almost surely a bot.
    case Bot = 'bot';

    // Too many links or a blocked word: kept in the spam folder.
    case Spam = 'spam';
}
