<?php

namespace Tests\Feature\Database;

use App\Enums\GameFormat;
use App\Enums\RuleStrength;
use App\Models\ApplicationSetting;
use App\Models\AvailabilitySlot;
use App\Models\Category;
use App\Models\FieldArea;
use App\Models\SchedulingRule;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gestpartits.initial_user.password' => 'ContrasenyaInicial2026']);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_initial_user_is_an_admin_who_must_change_the_password(): void
    {
        $user = User::query()->where('username', 'Gestor')->sole();

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->must_change_password);
    }

    public function test_categories_have_their_game_format(): void
    {
        $formats = Category::query()->orderBy('sort_order')->get()->mapWithKeys(fn (Category $c) => [$c->name => $c->game_format]);

        $this->assertSame([
            'Escoleta' => GameFormat::Football7,
            'Prebenjamí' => GameFormat::Football7,
            'Benjamí' => GameFormat::Football7,
            'Aleví' => GameFormat::Football7,
            'Infantil' => GameFormat::Football11,
            'Cadet' => GameFormat::Football11,
            'Juvenil' => GameFormat::Football11,
            'Primer equip' => GameFormat::Football11,
        ], $formats->all());
    }

    public function test_field_areas_and_slots_are_created(): void
    {
        $this->assertSame(['camp_1', 'camp_2', 'full'], FieldArea::query()->orderBy('sort_order')->pluck('code')->all());

        $slots = AvailabilitySlot::query()->orderBy('weekday')->orderBy('opens_at')->get()
            ->map(fn (AvailabilitySlot $s) => [$s->weekday, $s->opens_at, $s->last_kickoff_at, $s->default_fill_order])
            ->all();

        $this->assertSame([
            [6, '09:00:00', '13:00:00', 1],
            [6, '17:00:00', '19:00:00', null],
            [7, '09:00:00', '13:00:00', 2],
        ], $slots);
    }

    public function test_example_teams_and_initial_rules_are_created(): void
    {
        $this->assertSame(10, Team::query()->count());

        $escoleta = SchedulingRule::query()->whereHas('category', fn ($q) => $q->where('slug', 'escoleta'))->sole();
        $this->assertSame(RuleStrength::Mandatory, $escoleta->strength);
        $this->assertSame('10:00:00', $escoleta->not_before);

        $primer = SchedulingRule::query()->whereHas('team', fn ($q) => $q->where('slug', 'primer-equip'))->sole();
        $this->assertSame([6, '17:00:00'], [$primer->weekday, $primer->preferred_time]);

        $this->assertSame(6, SchedulingRule::query()->count());
    }

    public function test_templates_and_settings_are_created(): void
    {
        $this->assertSame(['partit_casa', 'partit_fora', 'resum_jornada'], WhatsAppTemplate::query()->orderBy('id')->pluck('code')->all());
        $this->assertSame(15, ApplicationSetting::get('kickoff_step_min'));
        $this->assertSame(['first_kickoff' => '09:00', 'last_kickoff' => '21:00'], ApplicationSetting::get('operating_hours'));
        $this->assertSame('#0B4EA2', ApplicationSetting::get('branding')['primary_color']);
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertSame(8, Category::query()->count());
        $this->assertSame(10, Team::query()->count());
        $this->assertSame(3, AvailabilitySlot::query()->count());
        $this->assertSame(6, SchedulingRule::query()->count());
        $this->assertSame(3, WhatsAppTemplate::query()->count());
    }
}
