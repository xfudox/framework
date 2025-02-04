<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @group one-of-many
 */
class DatabaseEloquentHasOneOfManyTest extends TestCase
{
    protected function setUp(): void
    {
        $db = new DB;

        $db->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        $db->bootEloquent();
        $db->setAsGlobal();

        $this->createSchema();
    }

    /**
     * Setup the database schema.
     *
     * @return void
     */
    public function createSchema()
    {
        $this->schema()->create('users', function ($table) {
            $table->increments('id');
        });

        $this->schema()->create('logins', function ($table) {
            $table->increments('id');
            $table->foreignId('user_id');
        });

        $this->schema()->create('states', function ($table) {
            $table->increments('id');
            $table->string('state');
            $table->string('type');
            $table->foreignId('user_id');
        });

        $this->schema()->create('prices', function ($table) {
            $table->increments('id');
            $table->dateTime('published_at');
            $table->foreignId('user_id');
        });
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('users');
        $this->schema()->drop('logins');
    }

    public function testItGuessesRelationName()
    {
        $user = HasOneOfManyTestUser::create();
        $this->assertSame('latest_login', $user->latest_login()->getRelationName());
    }

    // public function testRelationNameCanBeSet()
    // {
    //     $user = HasOneOfManyTestUser::create();
    //     $this->assertSame('foo', $user->latest_login_with_other_name()->getRelationName());
    // }

    public function testQualifyingSubSelectColumn()
    {
        $user = HasOneOfManyTestUser::create();
        $this->assertSame('latest_login.id', $user->latest_login()->qualifySubSelectColumn('id'));
    }

    public function testItFailsWhenUsingInvalidAggregate()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid aggregate [count] used within ofMany relation. Available aggregates: MIN, MAX');
        $user = HasOneOfManyTestUser::make();
        $user->latest_login_with_invalid_aggregate();
    }

    public function testItGetsCorrectResults()
    {
        $user = HasOneOfManyTestUser::create();
        $previousLogin = $user->logins()->create();
        $latestLogin = $user->logins()->create();

        $result = $user->latest_login()->getResults();
        $this->assertNotNull($result);
        $this->assertSame($latestLogin->id, $result->id);
    }

    public function testItGetsCorrectResultsUsingShortcutMethod()
    {
        $user = HasOneOfManyTestUser::create();
        $previousLogin = $user->logins()->create();
        $latestLogin = $user->logins()->create();

        $result = $user->latest_login_with_shortcut()->getResults();
        $this->assertNotNull($result);
        $this->assertSame($latestLogin->id, $result->id);
    }

    public function testItGetsCorrectResultsUsingShortcutReceivingMultipleColumnsMethod()
    {
        $user = HasOneOfManyTestUser::create();
        $user->prices()->create([
            'published_at' => '2021-05-01 00:00:00',
        ]);
        $price = $user->prices()->create([
            'published_at' => '2021-05-01 00:00:00',
        ]);

        $result = $user->price_with_shortcut()->getResults();
        $this->assertNotNull($result);
        $this->assertSame($price->id, $result->id);
    }

    public function testKeyIsAddedToAggregatesWhenMissing()
    {
        $user = HasOneOfManyTestUser::create();
        $user->prices()->create([
            'published_at' => '2021-05-01 00:00:00',
        ]);
        $price = $user->prices()->create([
            'published_at' => '2021-05-01 00:00:00',
        ]);

        $result = $user->price_without_key_in_aggregates()->getResults();
        $this->assertNotNull($result);
        $this->assertSame($price->id, $result->id);
    }

    public function testItGetsWithConstraintsCorrectResults()
    {
        $user = HasOneOfManyTestUser::create();
        $previousLogin = $user->logins()->create();
        $user->logins()->create();

        $result = $user->latest_login()->whereKey($previousLogin->getKey())->getResults();
        $this->assertNull($result);
    }

    public function testItEagerLoadsCorrectModels()
    {
        $user = HasOneOfManyTestUser::create();
        $user->logins()->create();
        $latestLogin = $user->logins()->create();

        $user = HasOneOfManyTestUser::with('latest_login')->first();

        $this->assertTrue($user->relationLoaded('latest_login'));
        $this->assertSame($latestLogin->id, $user->latest_login->id);
    }

    public function testHasNested()
    {
        $user = HasOneOfManyTestUser::create();
        $previousLogin = $user->logins()->create();
        $latestLogin = $user->logins()->create();

        $found = HasOneOfManyTestUser::whereHas('latest_login', function ($query) use ($latestLogin) {
            $query->where('logins.id', $latestLogin->id);
        })->exists();
        $this->assertTrue($found);

        $found = HasOneOfManyTestUser::whereHas('latest_login', function ($query) use ($previousLogin) {
            $query->where('logins.id', $previousLogin->id);
        })->exists();
        $this->assertFalse($found);
    }

    public function testHasCount()
    {
        $user = HasOneOfManyTestUser::create();
        $user->logins()->create();
        $user->logins()->create();

        $user = HasOneOfManyTestUser::withCount('latest_login')->first();
        $this->assertEquals(1, $user->latest_login_count);
    }

    public function testExists()
    {
        $user = HasOneOfManyTestUser::create();
        $previousLogin = $user->logins()->create();
        $latestLogin = $user->logins()->create();

        $this->assertFalse($user->latest_login()->whereKey($previousLogin->getKey())->exists());
        $this->assertTrue($user->latest_login()->whereKey($latestLogin->getKey())->exists());
    }

    public function testIsMethod()
    {
        $user = HasOneOfManyTestUser::create();
        $login1 = $user->latest_login()->create();
        $login2 = $user->latest_login()->create();

        $this->assertFalse($user->latest_login()->is($login1));
        $this->assertTrue($user->latest_login()->is($login2));
    }

    public function testIsNotMethod()
    {
        $user = HasOneOfManyTestUser::create();
        $login1 = $user->latest_login()->create();
        $login2 = $user->latest_login()->create();

        $this->assertTrue($user->latest_login()->isNot($login1));
        $this->assertFalse($user->latest_login()->isNot($login2));
    }

    /**
     * @group fail
     */
    public function testGet()
    {
        $user = HasOneOfManyTestUser::create();
        $previousLogin = $user->logins()->create();
        $latestLogin = $user->logins()->create();

        $latestLogins = $user->latest_login()->get();
        $this->assertCount(1, $latestLogins);
        $this->assertSame($latestLogin->id, $latestLogins->first()->id);

        $latestLogins = $user->latest_login()->whereKey($previousLogin->getKey())->get();
        $this->assertCount(0, $latestLogins);
    }

    public function testCount()
    {
        $user = HasOneOfManyTestUser::create();
        $user->logins()->create();
        $user->logins()->create();

        $this->assertSame(1, $user->latest_login()->count());
    }

    public function testAggregate()
    {
        $user = HasOneOfManyTestUser::create();
        $firstLogin = $user->logins()->create();
        $user->logins()->create();

        $user = HasOneOfManyTestUser::first();
        $this->assertSame($firstLogin->id, $user->first_login->id);
    }

    public function testJoinConstraints()
    {
        $user = HasOneOfManyTestUser::create();
        $user->states()->create([
            'type'  => 'foo',
            'state' => 'draft',
        ]);
        $currentForState = $user->states()->create([
            'type'  => 'foo',
            'state' => 'active',
        ]);
        $user->states()->create([
            'type'  => 'bar',
            'state' => 'baz',
        ]);

        $user = HasOneOfManyTestUser::first();
        $this->assertSame($currentForState->id, $user->foo_state->id);
    }

    public function testMultipleAggregates()
    {
        $user = HasOneOfManyTestUser::create();

        $user->prices()->create([
            'published_at' => '2021-05-01 00:00:00',
        ]);
        $price = $user->prices()->create([
            'published_at' => '2021-05-01 00:00:00',
        ]);

        $user = HasOneOfManyTestUser::first();
        $this->assertSame($price->id, $user->price->id);
    }

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }
}

/**
 * Eloquent Models...
 */
class HasOneOfManyTestUser extends Eloquent
{
    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = false;

    public function logins()
    {
        return $this->hasMany(HasOneOfManyTestLogin::class, 'user_id');
    }

    public function latest_login()
    {
        return $this->hasOne(HasOneOfManyTestLogin::class, 'user_id')->ofMany();
    }

    public function latest_login_with_shortcut()
    {
        return $this->hasOne(HasOneOfManyTestLogin::class, 'user_id')->latestOfMany();
    }

    public function latest_login_with_invalid_aggregate()
    {
        return $this->hasOne(HasOneOfManyTestLogin::class, 'user_id')->ofMany('id', 'count');
    }

    public function first_login()
    {
        return $this->hasOne(HasOneOfManyTestLogin::class, 'user_id')->ofMany('id', 'min');
    }

    public function states()
    {
        return $this->hasMany(HasOneOfManyTestState::class, 'user_id');
    }

    public function foo_state()
    {
        return $this->hasOne(HasOneOfManyTestState::class, 'user_id')->ofMany(
            ['id' => 'max'],
            function ($q) {
                $q->where('type', 'foo');
            }
        );
    }

    public function prices()
    {
        return $this->hasMany(HasOneOfManyTestPrice::class, 'user_id');
    }

    public function price()
    {
        return $this->hasOne(HasOneOfManyTestPrice::class, 'user_id')->ofMany([
            'published_at' => 'max',
            'id'           => 'max',
        ], function ($q) {
            $q->where('published_at', '<', now());
        });
    }

    public function price_without_key_in_aggregates()
    {
        return $this->hasOne(HasOneOfManyTestPrice::class, 'user_id')->ofMany(['published_at' => 'MAX']);
    }

    public function price_with_shortcut()
    {
        return $this->hasOne(HasOneOfManyTestPrice::class, 'user_id')->latestOfMany(['published_at', 'id']);
    }
}

class HasOneOfManyTestLogin extends Eloquent
{
    protected $table = 'logins';
    protected $guarded = [];
    public $timestamps = false;
}

class HasOneOfManyTestState extends Eloquent
{
    protected $table = 'states';
    protected $guarded = [];
    public $timestamps = false;
    protected $fillable = ['type', 'state'];
}

class HasOneOfManyTestPrice extends Eloquent
{
    protected $table = 'prices';
    protected $guarded = [];
    public $timestamps = false;
    protected $fillable = ['published_at'];
    protected $casts = ['published_at' => 'datetime'];
}
