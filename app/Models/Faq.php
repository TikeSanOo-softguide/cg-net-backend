<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title_en', 'title_zh', 'title_my', 'description_en', 'description_zh', 'description_my'])]
class Faq extends Model {}
