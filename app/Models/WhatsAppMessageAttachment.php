<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'whatsapp_message_attachments')]
#[Fillable(['whatsapp_message_id', 'generated_image_id', 'sort_order'])]
class WhatsAppMessageAttachment extends Model
{
    /** @return BelongsTo<WhatsAppMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'whatsapp_message_id');
    }

    /** @return BelongsTo<GeneratedImage, $this> */
    public function generatedImage(): BelongsTo
    {
        return $this->belongsTo(GeneratedImage::class);
    }
}
