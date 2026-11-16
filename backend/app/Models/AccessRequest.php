<?php

namespace App\Models;

use Database\Factories\AccessRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A prospective customer asking for an organization, sent from the landing page.
 *
 * @property int $id
 * @property string $name
 * @property string $company
 * @property string $email
 * @property string|null $phone
 * @property string|null $message
 * @property string $language
 * @property string $status
 */
class AccessRequest extends Model
{
    /** @use HasFactory<AccessRequestFactory> */
    use HasFactory;

    /** new = not answered yet, contacted = in conversation, closed = done (set up or declined). */
    public const STATUSES = ['new', 'contacted', 'closed'];

    public const LANGUAGES = ['en', 'bn'];

    /** The same email can send another request only after this many hours. */
    public const REPEAT_AFTER_HOURS = 24;

    protected $fillable = ['name', 'company', 'email', 'phone', 'message', 'language', 'status'];
}
