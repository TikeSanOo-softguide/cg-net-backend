<?php

namespace App\Enums;

enum OtpVerificationResult
{
    case Valid;
    case Invalid;
    case Expired;
    case MaxAttemptsExceeded;
}
