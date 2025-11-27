<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar_url',
        'division',
        'is_division_head',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $appends = [
        'avatar_url',
        'avatar_initials',
    ];

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withTimestamps();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function assignedTickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_users');
    }

     public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    public function weeklyTodos(): HasMany
    {
        return $this->hasMany(WeeklyTodo::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'current_approver_id');
    }

    public function employeeLeaveRequests(): HasMany
    {
        return $this->hasManyThrough(
            LeaveRequest::class,
            Employee::class,
            'user_id',
            'employee_id',
            'id',
            'id'
        );
    }

    public function isAssignedToTicket(Ticket $ticket): bool
    {
        return $this->assignedTickets()->where('ticket_id', $ticket->id)->exists();
    }

    public function assignToTicket(Ticket $ticket): void
    {
        $this->assignedTickets()->syncWithoutDetaching($ticket->id);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->orderBy('created_at', 'desc');
    }

    public function unreadNotifications(): HasMany
    {
        return $this->hasMany(Notification::class)->unread()->orderBy('created_at', 'desc');
    }

    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->unreadNotifications()->count();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $roles = Role::all()->pluck('name')->toArray();
        return $this->hasAnyRole($roles);
    }

    public function getAvatarUrlAttribute(): string
    {
        // If avatar_url is stored in database, use it
        if (!empty($this->attributes['avatar_url'] ?? null)) {
            return $this->attributes['avatar_url'];
        }

        // Fallback to ui-avatars.com for all users (including Google users without stored avatar)
        $name = trim($this->name ?: ($this->email ?? 'User'));
        $background = 'E0E7FF';
        $color = '312E81';

        return 'https://ui-avatars.com/api/?' . http_build_query([
            'name' => $name,
            'background' => $background,
            'color' => $color,
            'size' => 128,
            'bold' => true,
        ]);
    }

    public function getAvatarInitialsAttribute(): string
    {
        if (! $this->name) {
            return '';
        }

        $segments = collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->map(fn (string $segment) => Str::upper(Str::substr($segment, 0, 1)));

        return Str::limit($segments->take(2)->implode(''), 2, '');
    }
}
