<?php

return [

    'free_message_limit' => (int) env('CHAT_FREE_MESSAGE_LIMIT', 3),

    'bad_words' => array_values(array_filter(array_map('trim', explode(',', env('CHAT_BAD_WORDS', ''))))),

];
