<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Lang, Setting};
use App\Models\Order;

// The page about the person in need, filled in by an admin on the settings page.
final class StoryController extends Controller
{
    // disability: has a disability; operation: needs surgery; treatment: long treatment.
    public const CONDITIONS = ['disability', 'operation', 'treatment', 'other'];

    public static function person(): array
    {
        return [
            'name' => Setting::get('person_name'),
            'age' => (int) Setting::get('person_age', '0'),
            'condition' => Setting::get('person_condition'),
            'health' => Setting::get('person_health'),
            'need' => Setting::get('person_need'),
        ];
    }

    // The story page exists once the admin wrote a name or a story.
    public static function exists(): bool
    {
        return Setting::get('person_name') !== '' || Setting::get('story_text') !== '';
    }

    public function show(): void
    {
        if (!self::exists()) {
            $this->notFound('story_missing');
            return;
        }
        $person = self::person();
        $this->view('story', [
            'title' => Setting::get('story_title') ?: ($person['name'] ?: Lang::t('story_default_title')),
            'person' => $person,
            'text' => Setting::get('story_text'),
            'photo' => Setting::get('story_photo'),
            'goal' => (int) Setting::get('goal_amount', '0'),
            'raised' => Order::totalRaised(),
        ]);
    }
}
