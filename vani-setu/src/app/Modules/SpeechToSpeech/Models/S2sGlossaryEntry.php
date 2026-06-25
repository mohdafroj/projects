<?php

namespace App\Modules\SpeechToSpeech\Models;

use Illuminate\Database\Eloquent\Model;

class S2sGlossaryEntry extends Model
{
    protected $table = 's2s_glossary';

    protected $fillable = [
        'src_lang',
        'tgt_lang',
        'source_term',
        'target_term',
        'pronunciation',
        'notes',
    ];
}
