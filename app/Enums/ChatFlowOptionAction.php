<?php

namespace App\Enums;

enum ChatFlowOptionAction: string
{
    case GoToStep = 'go_to_step';
    case GoToUrl = 'go_to_url';
    case ReplyText = 'reply_text';
    case TransferAgent = 'transfer_agent';
    case MainMenu = 'main_menu';
    case CloseChat = 'close_chat';
}
