<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ObserverInvitation extends Model
{
    protected $fillable = ['prepared_by_user_id', 'competitive_bidding_record_id', 'payload', 'revision'];

    protected $casts = ['payload' => 'array', 'revision' => 'integer'];

    public function biddingRecord(): BelongsTo
    {
        return $this->belongsTo(CompetitiveBiddingRecord::class, 'competitive_bidding_record_id');
    }

    public function copies(): MorphMany
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable')->active()->latest();
    }

    public function recipient(): string
    {
        return ($this->payload['recipient_name'] ?? '') ?: 'Unnamed observer';
    }

    public static function defaults(): array
    {
        $schedule = ['when' => '', 'projects' => [['description' => '', 'budget_prefix' => 'with an ABC of Php', 'budget' => '']]];

        return [
            'republic' => 'Republic of the Philippines',
            'province' => 'Province of Southern Leyte',
            'municipality' => 'MUNICIPALITY OF TOMAS OPPUS',
            'separator' => '-oOo-',
            'office_heading' => 'OFFICE OF THE BAC CHAIRPERSON',
            'letter_date' => now('Asia/Manila')->format('F j, Y'),
            'recipient_name' => '',
            'recipient_details' => '',
            'salutation' => 'Dear Observer,',
            'greeting' => 'Greetings!',
            'introduction' => "The LGU Tomas Oppus, Southern Leyte, Bids and Award Committee is scheduled to conduct a Pre-bid Conference and Bid Opening to be held at the Mayor’s Conference Room on the following projects and scheduled dates to wit:",
            'sections' => [
                ['heading' => 'PRE-BID CONFERENCE:', 'schedules' => [$schedule]],
                ['heading' => 'BID OPENING:', 'schedules' => [$schedule]],
            ],
            'request_paragraph' => 'In connection thereto, may the undersigned request your attendance at the said bidding as an observer.',
            'thank_you' => 'Thank you and God Bless.',
            'closing' => 'Respectfully yours,',
            'signatory_name' => '',
            'signatory_position' => 'BAC Chairperson',
        ];
    }
}
