<?php

namespace App;


use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\AuditingTrait;


/**
 * @property mixed type
 * @property float latitude
 * @property float longitude
 * @property mixed created_at
 * @property mixed updated_at
 * @property mixed deleted_at
 */
class Tournament extends Model
{
    use SoftDeletes;


    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable()
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    protected $table = 'tournament';
    public $timestamps = true;

    protected $fillable = [
        'name',
        'dateIni',
        'dateFin',
        'registerDateLimit',
        'sport',
        'promoter',
        'host_organization',
        'technical_assistance',
        'category',
        'rule_id',
        'type',
        'venue_id',
        'level_id'
    ];


    protected $dates = ['dateIni', 'dateFin', 'registerDateLimit', 'created_at', 'updated_at', 'deleted_at'];

    protected static function boot()
    {
        parent::boot();
        static::deleting(function ($tournament) {
            foreach ($tournament->championships as $ct) {
                $ct->delete();
            }
            $tournament->invites()->delete();

        });
        static::restoring(function ($tournament) {

            foreach ($tournament->championships()->withTrashed()->get() as $ct) {
                $ct->restore();
            }

        });

    }


    /**
     * A tournament is owned by a user
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get All Tournaments levels
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function level()
    {
        return $this->belongsTo(TournamentLevel::class, 'level_id', 'id');
    }

    /**
     * Get Full venue object
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }


    /**
     * We can use $tournament->categories()->attach(id);
     * Or         $tournament->categories()->sync([1, 2, 3]);
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'championship')
            ->withPivot('id')
            ->withTimestamps();
    }

    /**
     * Get All categoriesTournament that belongs to a tournament
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function championships()
    {
        return $this->hasMany(Championship::class);
    }


    /**
     * Get All categoriesSettings that belongs to a tournament
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function championshipSettings()
    {
        return $this->hasManyThrough(ChampionshipSettings::class, Championship::class);
    }

    /**
     * çGet All teams that belongs to a tournament
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function teams()
    {
        return $this->hasManyThrough(Team::class, Championship::class);
    }

    /**
     * Get All competitors that belongs to a tournament
     * @param null $championshipId
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function competitors($championshipId = null)
    {
        return $this->hasManyThrough(Competitor::class, Championship::class);
    }

    /**
     * Get All trees that belongs to a tournament
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function trees()
    {
        return $this->hasManyThrough(Tree::class, Championship::class);
    }


    /**
     * Get all Invitations that belongs to a tournament
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function invites()
    {
        return $this->morphMany(Invite::class, 'object');
    }

    /**
     * Get Category List with <Select> Format
     * @return mixed
     */
    public function getCategoryList()
    {
        return $this->categories->pluck('id')->all();
    }


    public function getDateAttribute($date)
    {
        return $date;
    }

    public function getRegisterDateLimitAttribute($date)
    {
        return $date;
    }

    public function getDateIniAttribute($date)
    {
        return $date;
    }

    public function getDateFinAttribute($date)
    {
        return $date;
    }

    /**
     * Check if the tournament is Open
     * @return bool
     */
    public function isOpen()
    {
        return $this->type == 1;
    }

    /**
     * * Check if the tournament needs Invitation
     * @return bool
     */
    public function needsInvitation()
    {
        return $this->type == 0;
    }

    /**
     * @return bool
     */
    public function isInternational()
    {
        return $this->level_id == 8;
    }

    /**
     * @return bool
     */
    public function isNational()
    {
        return $this->level_id == 7;
    }

    /**
     * @return bool
     */
    public function isRegional()
    {
        return $this->level_id == 6;
    }

    /**
     * @return bool
     */
    public function isEstate()
    {
        return $this->level_id == 5;
    }

    /**
     * @return bool
     */
    public function isMunicipal()
    {
        return $this->level_id == 4;
    }

    /**
     * @return bool
     */
    public function isDistrictal()
    {
        return $this->level_id == 3;
    }

    /**
     * @return bool
     */
    public function isLocal()
    {
        return $this->level_id == 2;
    }

    /**
     * @return bool
     */
    public function hasNoLevel()
    {
        return $this->level_id == 1;
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted_at != null;
    }


    /**
     * Create and Configure Championships depending the rule ( IKF, EKF, LAKF, etc )
     * @param $ruleId
     */
    public function setAndConfigureCategories($ruleId)
    {
        if ($ruleId == 0) return; // No Rules Selected

        $options = $this->loadRulesOptions($ruleId);

        // Create Tournament Categories
        $arrCategories = array_keys($options);
        $this->categories()->sync($arrCategories);

        // Configure each category creating categorySetting Object

        foreach ($this->championships as $championship) {
            $rules = $options[$championship->category->id];
            $rules['championship_id'] = $championship->id;
            ChampionshipSettings::create($rules);
        }

    }

    private function loadRulesOptions($ruleId)
    {
        switch ($ruleId) {
            case 0: // No preset selected
                return null;
            case 1:
                return $options = config('options.ikf_settings');
                break;
            case 2:
                return $options = config('options.ekf_settings');
                break;
            case 3:
                return $options = config('options.lakc_settings');
                break;
            default:
                return null;
        }
    }

    /**
     * create a category List with Category name associated to championshipId
     *
     * @return array
     */
    public function buildCategoryList()
    {
        $cts = Championship::with('category','settings')
            ->whereHas('category', function($query){
                return $query->where('isTeam',1);

            })
            ->where('tournament_id', $this->id)
            ->get();
        $array = [];
        foreach ($cts as $ct) {

            $array[$ct->id] = $ct->category->alias != ''
                ? $ct->category->alias
                : trim($ct->category->buildName());

        }

        return $array;

    }


    /**
     * Check if the tournament has a Team Championship
     * @return integer
     */
    public function hasTeamCategory()
    {
        return $this
            ->categories()
            ->where('isTeam', '1')
            ->count();
    }


}