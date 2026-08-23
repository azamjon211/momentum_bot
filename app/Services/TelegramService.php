<?php

namespace App\Services;
use App\Enums\TaskType;
use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\Friendship;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
class TelegramService
{
    private const MENU_TODAY = '📋 Bugungi tasklarim';
    private const MENU_FRIENDS = '👥 Do\'stlarim';
    private const MENU_INVITE = '➕ Do\'st taklif qilish';
    private const MENU_CREATE_PLAN = '🗓 Reja yaratish';
    private const MENU_MANAGE_PLANS = '✏️ Rejalarni boshqarish';
    private const MENU_HELP = 'ℹ️ Yordam';

    private const WEEKDAY_BUTTONS = [
        ['monday', 'Dushanba'], ['tuesday', 'Seshanba'], ['wednesday', 'Chorshanba'],
        ['thursday', 'Payshanba'], ['friday', 'Juma'], ['saturday', 'Shanba'], ['sunday', 'Yakshanba'],
    ];

    private const WEEKDAY_LABELS = [
        'monday' => 'Dushanba', 'tuesday' => 'Seshanba', 'wednesday' => 'Chorshanba',
        'thursday' => 'Payshanba', 'friday' => 'Juma', 'saturday' => 'Shanba', 'sunday' => 'Yakshanba',
    ];

    private const WEEKDAY_GROUPS = [
        'everyday' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'weekend' => ['saturday', 'sunday'],
    ];

    private const WEEKDAY_GROUP_LABELS = [
        'everyday' => 'Har kuni',
        'weekdays' => 'Ish kunlari (Dush-Juma)',
        'weekend' => 'Dam olish kunlari (Shan-Yak)',
    ];

    private const TYPE_LABELS = [
        'checkbox' => 'Oddiy (belgilash)', 'duration' => 'Vaqt (daqiqa/soat)', 'count' => 'Miqdor (sahifa/marta)',
    ];

    private const COMPLETION_MESSAGES = [
        "🔥 Zo'r! \"{title}\" bajarildi!",
        "💪 Ajoyib ish! \"{title}\" tugadi.",
        "⭐ Yana bir g'alaba! \"{title}\" bajarildi.",
        "🚀 Davom eting! \"{title}\" bajarildi.",
        "🏆 Zo'rsiz! \"{title}\" — bajarildi.",
        "✨ Yaxshi natija! \"{title}\" tugadi.",
    ];

    public function __construct(
        private DailyPlanService $dailyPlanService,
        private FriendshipService $friendshipService,
        private WeeklyPlanService $weeklyPlanService,
    ) {}

    public function handleUpdate(array $update){
    if(isset($update['message'])){
        $this->handleMessage($update['message']);
    }
    if(isset($update['callback_query'])){
        $this->handleCallbackQuery($update['callback_query']);
    }
    }

    private function handleMessage(array $message){
        $text = trim($message['text'] ?? '');
        $telegramId = $message['from']['id'];
        $chatId = $message['chat']['id'];
        $firstName = $message['from']['first_name'] ?? 'Foydalanuvchi';

        [$command, $payload] = array_pad(explode(' ', $text, 2), 2, null);

        if($command === '/start'){
            $this->handleStart($telegramId, $chatId, $firstName, $payload);
            return;
        }
        if($command === '/invite' || $text === self::MENU_INVITE){
            $this->handleInvite($telegramId, $chatId);
            return;
        }
        if($command === '/bugun' || $text === self::MENU_TODAY){
            $this->handleToday($telegramId, $chatId);
            return;
        }
        if($text === self::MENU_FRIENDS){
            $this->handleFriendsList($telegramId, $chatId);
            return;
        }
        if($command === '/reja' || $text === self::MENU_CREATE_PLAN){
            $this->handleCreatePlanStart($telegramId, $chatId);
            return;
        }
        if($text === self::MENU_MANAGE_PLANS){
            $this->handleManagePlans($telegramId, $chatId);
            return;
        }
        if($command === '/help' || $text === self::MENU_HELP){
            $this->handleHelp($chatId);
            return;
        }

        $user = User::where('telegram_id', $telegramId)->first();
        if($user && $user->bot_state){
            $this->handleWizardText($user, $chatId, $text);
        }
    }

    public function handleStart(int $telegramId, int $chatId, string $firstName, ?string $inviteCode = null){
        $user = User::where('telegram_id', $telegramId) ->first();
        if(!$user){
            $user = User::create([
                'telegram_id' => $telegramId,
                'name' => $firstName,
                'email' => "tg_{$telegramId}@telegram.local",
                'password' => bcrypt(str()->random(32)),
                'timezone'=>'Asia/Tashkent',
                'invite_code' => $this->friendshipService->generateInviteCode(),
            ]);
            $this->sendMessage($chatId, "Xush Kelibsiz, {$firstName}! Ro'yxatdan o'tdingiz");
        } else {
            $this->sendMessage($chatId, "Xush kelibsiz qaytib, {$firstName}");
        }

        if($inviteCode){
            $this->handleFriendInvite($user, $inviteCode);
        }

        $this->sendMainMenu($chatId, "Quyidagi menyudan foydalaning:");
    }

    private function handleToday(int $telegramId, int $chatId){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user){
            $this->sendMessage($chatId, "Avval /start bosing.");
            return;
        }

        $dailyPlan = DailyPlan::where('user_id', $user->id)
            ->whereDate('date', now($user->timezone)->toDateString())
            ->with('tasks')
            ->first();

        $tasks = $dailyPlan?->tasks ?? collect();

        if($tasks->isEmpty()){
            $this->sendMessage($chatId, "Bugun uchun task yo'q. Avval \"".self::MENU_CREATE_PLAN."\" orqali haftalik reja yarating.");
            return;
        }

        $buttons = $tasks->map(function($task){
            $label = $task->is_done ? "✅ {$task->title}" : "⬜ {$task->title}";
            return [['text' => $label, 'callback_data' => "confirm_task:{$task->id}"]];
        })->all();

        $this->sendMessage($chatId, "📋 <b>Bugungi tasklaringiz</b> (bajarish uchun bosing):", $buttons, 'HTML');
    }

    private function handleFriendsList(int $telegramId, int $chatId){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user){
            $this->sendMessage($chatId, "Avval /start bosing.");
            return;
        }

        $friends = $this->friendshipService->acceptedFriendsOf($user);

        if($friends->isEmpty()){
            $this->sendMessage($chatId, "Hali do'stlaringiz yo'q. \"".self::MENU_INVITE."\" tugmasini bosib taklif yuboring.");
            return;
        }

        $list = $friends->map(fn($friend) => "• {$friend->name}")->implode("\n");
        $this->sendMessage($chatId, "Sizning do'stlaringiz:\n{$list}");
    }

    private function handleHelp(int $chatId){
        $this->sendMainMenu($chatId, "Mavjud bo'limlar:");
    }

    private function sendMainMenu(int $chatId, string $text){
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode([
                'keyboard' => [
                    [self::MENU_TODAY, self::MENU_CREATE_PLAN],
                    [self::MENU_MANAGE_PLANS, self::MENU_FRIENDS],
                    [self::MENU_INVITE, self::MENU_HELP],
                ],
                'resize_keyboard' => true,
            ]),
        ];
        Http::post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage', $payload);
    }

    // ---------- Plan creation / editing wizard (shared text-input router) ----------

    private function handleWizardText(User $user, int $chatId, string $text){
        $normalized = mb_strtolower(trim($text));
        if($normalized === 'bekor qilish'){
            $this->cancelWizard($user, $chatId);
            return;
        }

        match($user->bot_state){
            'awaiting_plan_name' => $this->stepPlanName($user, $chatId, $text),
            'awaiting_task_title' => $this->stepTaskTitle($user, $chatId, $text),
            'awaiting_task_value_unit' => $this->stepTaskValueUnit($user, $chatId, $text),
            'awaiting_task_reminder_time' => $this->stepTaskReminderTime($user, $chatId, $text),
            'editing_plan_rename' => $this->stepEditPlanRename($user, $chatId, $text),
            'editing_task_title' => $this->stepEditTaskTitle($user, $chatId, $text),
            'editing_task_value' => $this->stepEditTaskValue($user, $chatId, $text),
            'editing_task_reminder' => $this->stepEditTaskReminder($user, $chatId, $text),
            default => null,
        };
    }

    private function currentMode(User $user): string
    {
        return $user->bot_state_data['mode'] ?? 'create';
    }

    private function cancelWizard(User $user, int $chatId){
        $data = $user->bot_state_data ?? [];
        $mode = $data['mode'] ?? null;

        if($mode === 'create' && !empty($data['weekly_plan_id'])){
            $plan = WeeklyPlan::find($data['weekly_plan_id']);
            $plan?->tasks()->delete();
            $plan?->delete();
        }

        $user->update(['bot_state' => null, 'bot_state_data' => null]);
        $this->sendMessage($chatId, "Bekor qilindi.");
    }

    // ---------- New plan creation ----------

    private function handleCreatePlanStart(int $telegramId, int $chatId){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user){
            $this->sendMessage($chatId, "Avval /start bosing.");
            return;
        }

        $user->update(['bot_state' => 'awaiting_plan_name', 'bot_state_data' => null]);
        $this->sendMessage($chatId, "Yangi reja nomini kiriting (masalan: Sport rejasi):");
    }

    private function stepPlanName(User $user, int $chatId, string $text){
        $plan = $this->weeklyPlanService->create($user, $text);
        $user->update([
            'bot_state' => 'awaiting_plan_duration',
            'bot_state_data' => ['weekly_plan_id' => $plan->id, 'mode' => 'create'],
        ]);

        $this->sendMessage($chatId, "\"{$plan->name}\" — qancha muddatga mo'ljallangan?", [
            [['text' => '7 kun', 'callback_data' => 'plan_duration:7'], ['text' => '14 kun', 'callback_data' => 'plan_duration:14']],
            [['text' => '30 kun', 'callback_data' => 'plan_duration:30'], ['text' => '90 kun', 'callback_data' => 'plan_duration:90']],
            [['text' => '♾ Cheksiz (doimiy)', 'callback_data' => 'plan_duration:none']],
        ]);
    }

    // ---------- Shared task-adding steps (used by both "create" and "add to existing plan") ----------

    private function stepTaskTitle(User $user, int $chatId, string $text){
        $draft = $user->bot_state_data['draft_task'] ?? [];
        $draft['title'] = $text;
        $user->update(['bot_state' => 'awaiting_task_type', 'bot_state_data' => array_merge($user->bot_state_data, ['draft_task' => $draft])]);

        $this->sendMessage($chatId, "Task turi qanday?", [
            [['text' => self::TYPE_LABELS['checkbox'], 'callback_data' => 'task_type:checkbox']],
            [['text' => self::TYPE_LABELS['duration'], 'callback_data' => 'task_type:duration']],
            [['text' => self::TYPE_LABELS['count'], 'callback_data' => 'task_type:count']],
        ]);
    }

    private function stepTaskValueUnit(User $user, int $chatId, string $text){
        if(!preg_match('/^(\d+)\s+(.+)$/u', trim($text), $m)){
            $this->sendMessage($chatId, "Format noto'g'ri. Shu ko'rinishda yozing: 30 daqiqa");
            return;
        }

        $draft = $user->bot_state_data['draft_task'] ?? [];
        $draft['target_value'] = (int) $m[1];
        $draft['target_unit'] = $m[2];
        $user->update(['bot_state_data' => array_merge($user->bot_state_data, ['draft_task' => $draft])]);

        $this->askReminderChoice($user, $chatId);
    }

    private function stepTaskReminderTime(User $user, int $chatId, string $text){
        $text = trim($text);
        if(!preg_match('/^\d{1,2}:\d{2}$/', $text)){
            $this->sendMessage($chatId, "Vaqt H:i formatida bo'lishi kerak (masalan 07:00). Qaytadan yozing:");
            return;
        }

        $draft = $user->bot_state_data['draft_task'] ?? [];
        $draft['remind_at'] = $text;
        $user->update(['bot_state_data' => array_merge($user->bot_state_data, ['draft_task' => $draft])]);

        $this->saveDraftTaskAndAskMore($user, $chatId);
    }

    private function askReminderChoice(User $user, int $chatId){
        $user->update(['bot_state' => 'awaiting_task_reminder_choice']);
        $this->sendMessage($chatId, "Eslatma qo'yasizmi?", [
            [['text' => '⏰ Ha', 'callback_data' => 'task_reminder:yes'], ['text' => '⏭ Yo\'q', 'callback_data' => 'task_reminder:no']],
        ]);
    }

    private function sendWeekdayPrompt(int $chatId){
        $groupRows = [
            [['text' => '📅 Har kuni', 'callback_data' => 'task_weekday:everyday']],
            [
                ['text' => '💼 Ish kunlari', 'callback_data' => 'task_weekday:weekdays'],
                ['text' => '🌴 Dam olish kunlari', 'callback_data' => 'task_weekday:weekend'],
            ],
        ];

        $dayRows = collect(self::WEEKDAY_BUTTONS)
            ->map(fn($pair) => ['text' => $pair[1], 'callback_data' => "task_weekday:{$pair[0]}"])
            ->chunk(2)
            ->map(fn($chunk) => $chunk->values()->all())
            ->values()
            ->all();

        $this->sendMessage($chatId, "Task uchun qaysi kun(lar)?", [...$groupRows, ...$dayRows]);
    }

    private function saveDraftTaskAndAskMore(User $user, int $chatId){
        $planId = $user->bot_state_data['weekly_plan_id'] ?? null;
        $plan = WeeklyPlan::find($planId);
        $draft = $user->bot_state_data['draft_task'] ?? [];
        $weekdays = $draft['weekdays'] ?? [];
        $mode = $this->currentMode($user);

        if(!$plan || empty($weekdays) || empty($draft['title']) || empty($draft['type'])){
            $user->update(['bot_state' => null, 'bot_state_data' => null]);
            $this->sendMessage($chatId, "Xatolik yuz berdi. Qaytadan boshlang.");
            return;
        }

        foreach($weekdays as $weekday){
            $taskData = $draft;
            unset($taskData['weekdays']);
            $taskData['weekday'] = $weekday;
            $taskData['position'] = $plan->tasks()->count();
            $this->weeklyPlanService->addTask($plan, $taskData);
        }

        $daysLabel = $this->labelForWeekdaySet($weekdays);

        $user->update([
            'bot_state' => 'awaiting_more_tasks',
            'bot_state_data' => ['weekly_plan_id' => $planId, 'mode' => $mode],
        ]);

        $this->sendMessage($chatId, "✅ Qo'shildi: {$draft['title']} ({$daysLabel})");
        $this->sendMessage($chatId, "Yana task qo'shasizmi?", [
            [['text' => '➕ Ha', 'callback_data' => 'plan_more:yes'], ['text' => '✅ Tugatish', 'callback_data' => 'plan_more:no']],
        ]);
    }

    private function finalizePlanCreation(User $user, int $chatId){
        $planId = $user->bot_state_data['weekly_plan_id'] ?? null;
        $plan = WeeklyPlan::find($planId);

        if($plan && $plan->tasks()->count() > 0){
            $this->weeklyPlanService->activate($plan);
            $this->dailyPlanService->generateForDate($user, now($user->timezone));
            $taskCount = $plan->tasks()->count();
            $duration = $plan->duration_days ? "{$plan->duration_days} kun" : "Cheksiz";
            $text = "🎉 <b>Reja faollashtirildi!</b>\n\n"
                ."📌 {$plan->name}\n"
                ."📅 Muddat: {$duration}\n"
                ."📝 Tasklar: {$taskCount} ta\n\n"
                ."\"".self::MENU_TODAY."\" tugmasidan bugungi tasklaringizni ko'rishingiz mumkin.";
            $this->sendMessage($chatId, $text, null, 'HTML');
        } else {
            $plan?->delete();
            $this->sendMessage($chatId, "Hech qanday task qo'shilmadi, reja bekor qilindi.");
        }

        $user->update(['bot_state' => null, 'bot_state_data' => null]);
    }

    private function finalizeAddToExisting(User $user, int $chatId){
        $planId = $user->bot_state_data['weekly_plan_id'] ?? null;
        $plan = WeeklyPlan::find($planId);
        $user->update(['bot_state' => null, 'bot_state_data' => null]);

        $this->sendMessage($chatId, "✅ Tasklar rejaga qo'shildi.");
        if($plan){
            $this->showPlanManagementView($chatId, $plan);
        }
    }

    // ---------- Plan / task management (list, rename, activate, delete, edit) ----------

    private function handleManagePlans(int $telegramId, int $chatId){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user){
            $this->sendMessage($chatId, "Avval /start bosing.");
            return;
        }

        $plans = $user->weeklyPlans()->orderByDesc('is_active')->orderByDesc('id')->get();

        if($plans->isEmpty()){
            $this->sendMessage($chatId, "Hali rejangiz yo'q. Avval \"".self::MENU_CREATE_PLAN."\" orqali reja yarating.");
            return;
        }

        $rows = $plans->map(fn($plan) => [['text' => ($plan->is_active ? '✅ ' : '').$plan->name, 'callback_data' => "mp:{$plan->id}"]])->all();
        $this->sendMessage($chatId, "Qaysi rejani boshqaramiz?", $rows);
    }

    private function showPlanManagementView(int $chatId, WeeklyPlan $plan){
        $groups = $this->taskGroupsFor($plan);

        $rows = $groups->map(fn($g) => [['text' => "✏️ {$g['title']} ({$this->labelForWeekdaySet($g['weekdays'])})", 'callback_data' => "mtg:{$g['group_key']}"]])->all();

        $actionRow1 = [['text' => '✏️ Nomini o\'zgartirish', 'callback_data' => "rp:{$plan->id}"]];
        if(!$plan->is_active){
            $actionRow1[] = ['text' => '▶️ Faollashtirish', 'callback_data' => "ap:{$plan->id}"];
        }
        $actionRow2 = [
            ['text' => '➕ Task qo\'shish', 'callback_data' => "atp:{$plan->id}"],
            ['text' => '🗑 Rejani o\'chirish', 'callback_data' => "dp:{$plan->id}"],
        ];
        $backRow = [['text' => '⬅️ Rejalar ro\'yxati', 'callback_data' => 'back_to_plans']];

        $duration = $plan->duration_days ? "{$plan->duration_days} kun (tugaydi: {$plan->ends_at?->toDateString()})" : 'cheksiz';
        $text = "\"{$plan->name}\"".($plan->is_active ? ' (faol ✅)' : '')."\nMuddat: {$duration}";

        if($groups->isEmpty()){
            $text .= "\n\nHali task yo'q.";
        }

        $this->sendMessage($chatId, $text, [...$rows, $actionRow1, $actionRow2, $backRow]);
    }

    private function taskGroupsFor(WeeklyPlan $plan)
    {
        return $plan->tasks()->get()->groupBy('group_key')->map(function($rows, $key){
            $first = $rows->first();
            return [
                'group_key' => $key,
                'title' => $first->title,
                'type' => $first->type->value,
                'target_value' => $first->target_value,
                'target_unit' => $first->target_unit,
                'remind_at' => $first->remind_at,
                'weekdays' => $rows->pluck('weekday')->all(),
            ];
        })->values();
    }

    private function labelForWeekdaySet(array $weekdays): string
    {
        $order = array_keys(self::WEEKDAY_LABELS);
        $sorted = collect($order)->filter(fn($d) => in_array($d, $weekdays))->values()->all();

        foreach(self::WEEKDAY_GROUPS as $key => $set){
            $sortedSet = $set;
            sort($sortedSet);
            $cmp = $sorted;
            sort($cmp);
            if($sortedSet === $cmp){
                return self::WEEKDAY_GROUP_LABELS[$key];
            }
        }

        return implode(', ', array_map(fn($d) => self::WEEKDAY_LABELS[$d] ?? $d, $sorted));
    }

    private function showTaskGroupView(int $chatId, string $groupKey){
        $tasks = WeeklyPlanTask::where('group_key', $groupKey)->get();
        if($tasks->isEmpty()){
            $this->sendMessage($chatId, "Task topilmadi.");
            return;
        }

        $rep = $tasks->first();
        $typeLabel = self::TYPE_LABELS[$rep->type->value] ?? $rep->type->value;
        $valueText = $rep->target_value ? "{$rep->target_value} {$rep->target_unit}" : '-';
        $reminderText = $rep->remind_at ?? "yo'q";
        $daysLabel = $this->labelForWeekdaySet($tasks->pluck('weekday')->all());

        $text = "{$rep->title}\nKun(lar): {$daysLabel}\nTuri: {$typeLabel}\nQiymat: {$valueText}\nEslatma: {$reminderText}";

        $rows = [
            [['text' => '📝 Nomini o\'zgartirish', 'callback_data' => "etn:{$groupKey}"]],
        ];
        if($rep->type->value !== TaskType::Checkbox->value){
            $rows[] = [['text' => '🔢 Qiymat/birlik', 'callback_data' => "etv:{$groupKey}"]];
        }
        $rows[] = [['text' => '⏰ Eslatma', 'callback_data' => "etr:{$groupKey}"]];
        $rows[] = [['text' => '📅 Kun(lar)ni o\'zgartirish', 'callback_data' => "etd:{$groupKey}"]];
        $rows[] = [['text' => '🗑 O\'chirish', 'callback_data' => "dtg:{$groupKey}"]];
        $rows[] = [['text' => '⬅️ Orqaga', 'callback_data' => "mp:{$rep->weekly_plan_id}"]];

        $this->sendMessage($chatId, $text, $rows);
    }

    private function stepEditPlanRename(User $user, int $chatId, string $text){
        $planId = $user->bot_state_data['plan_id'] ?? null;
        $plan = WeeklyPlan::where('id', $planId)->where('user_id', $user->id)->first();
        $user->update(['bot_state' => null, 'bot_state_data' => null]);

        if(!$plan){
            $this->sendMessage($chatId, "Reja topilmadi.");
            return;
        }

        $plan->update(['name' => $text]);
        $this->sendMessage($chatId, "✅ Nomi yangilandi: {$text}");
        $this->showPlanManagementView($chatId, $plan);
    }

    private function stepEditTaskTitle(User $user, int $chatId, string $text){
        $groupKey = $user->bot_state_data['group_key'] ?? null;
        $user->update(['bot_state' => null, 'bot_state_data' => null]);

        if(!$groupKey || !$this->groupBelongsToUser($groupKey, $user)){
            $this->sendMessage($chatId, "Task topilmadi.");
            return;
        }

        WeeklyPlanTask::where('group_key', $groupKey)->update(['title' => $text]);
        $this->sendMessage($chatId, "✅ Nomi yangilandi: {$text}");
        $this->showTaskGroupView($chatId, $groupKey);
    }

    private function stepEditTaskValue(User $user, int $chatId, string $text){
        $groupKey = $user->bot_state_data['group_key'] ?? null;

        if(!preg_match('/^(\d+)\s+(.+)$/u', trim($text), $m)){
            $this->sendMessage($chatId, "Format noto'g'ri. Shu ko'rinishda yozing: 30 daqiqa");
            return;
        }

        $user->update(['bot_state' => null, 'bot_state_data' => null]);

        if(!$groupKey || !$this->groupBelongsToUser($groupKey, $user)){
            $this->sendMessage($chatId, "Task topilmadi.");
            return;
        }

        WeeklyPlanTask::where('group_key', $groupKey)->update(['target_value' => (int) $m[1], 'target_unit' => $m[2]]);
        $this->sendMessage($chatId, "✅ Qiymat yangilandi: {$m[1]} {$m[2]}");
        $this->showTaskGroupView($chatId, $groupKey);
    }

    private function stepEditTaskReminder(User $user, int $chatId, string $text){
        $groupKey = $user->bot_state_data['group_key'] ?? null;
        $normalized = mb_strtolower(trim($text));
        $clear = in_array($normalized, ["yo'q", 'yoq', 'yoq.', "yo'q."]);

        if(!$clear && !preg_match('/^\d{1,2}:\d{2}$/', trim($text))){
            $this->sendMessage($chatId, "Vaqt H:i formatida bo'lishi kerak (masalan 07:00), yoki eslatmani olib tashlash uchun \"yo'q\" deb yozing:");
            return;
        }

        $user->update(['bot_state' => null, 'bot_state_data' => null]);

        if(!$groupKey || !$this->groupBelongsToUser($groupKey, $user)){
            $this->sendMessage($chatId, "Task topilmadi.");
            return;
        }

        $remindAt = $clear ? null : trim($text);
        WeeklyPlanTask::where('group_key', $groupKey)->update(['remind_at' => $remindAt]);
        $this->sendMessage($chatId, $clear ? "✅ Eslatma olib tashlandi" : "✅ Eslatma vaqti: {$remindAt}");
        $this->showTaskGroupView($chatId, $groupKey);
    }

    private function groupBelongsToUser(string $groupKey, User $user): bool
    {
        $task = WeeklyPlanTask::where('group_key', $groupKey)->first();
        return $task && $task->weeklyPlan && $task->weeklyPlan->user_id === $user->id;
    }

    // ---------- Callback query routing ----------

    private function handleFriendInvite(User $user, string $inviteCode){
        $inviter = User::where('invite_code', $inviteCode)->first();

        if(!$inviter || $inviter->id === $user->id){
            return;
        }

        $friendship = $this->friendshipService->sendRequest($user, $inviter);

        if(!$friendship || $friendship->status !== 'pending'){
            return;
        }

        $this->sendMessage($user->telegram_id, "Do'stlik so'rovi {$inviter->name}ga yuborildi.");
        $this->sendMessage($inviter->telegram_id, "{$user->name} sizga do'st bo'lishni so'ramoqda", [
            [
                ['text' => '✅ Qabul qilish', 'callback_data' => "accept_friend:{$friendship->id}"],
                ['text' => '❌ Rad etish', 'callback_data' => "decline_friend:{$friendship->id}"],
            ],
        ]);
    }

    private function handleInvite(int $telegramId, int $chatId){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user){
            $this->sendMessage($chatId, "Avval /start bosing.");
            return;
        }
        $botUsername = config('services.telegram.bot_username');
        $link = "https://t.me/{$botUsername}?start={$user->invite_code}";
        $this->sendMessage($chatId, "Do'stlaringizni taklif qilish uchun shu havolani yuboring:\n{$link}");
    }

    private function handleCallbackQuery(array $callbackQuery){
        $callbackId = $callbackQuery['id'];
        $data = $callbackQuery['data'] ?? '';
        $chatId = $callbackQuery['message']['chat']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $telegramId = $callbackQuery['from']['id'] ?? null;

        if(str_starts_with($data, 'confirm_task:')){
            $this->handleConfirmTaskCallback($callbackId, $chatId, $data);
            return;
        }

        if(str_starts_with($data, 'cancel_task:')){
            $this->handleCancelTaskCallback($callbackId, $chatId, $messageId, $data);
            return;
        }

        if(str_starts_with($data, 'complete_task:')){
            $this->handleCompleteTaskCallback($callbackId, $chatId, $messageId, $data);
            return;
        }

        if(str_starts_with($data, 'accept_friend:') || str_starts_with($data, 'decline_friend:')){
            $this->handleFriendResponseCallback($callbackId, $chatId, $messageId, $data);
            return;
        }

        if(str_starts_with($data, 'plan_duration:')){
            $this->handlePlanDurationCallback($callbackId, $chatId, $messageId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'task_weekday:')){
            $this->handleTaskWeekdayCallback($callbackId, $chatId, $messageId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'task_type:')){
            $this->handleTaskTypeCallback($callbackId, $chatId, $messageId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'task_reminder:')){
            $this->handleTaskReminderCallback($callbackId, $chatId, $messageId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'plan_more:')){
            $this->handlePlanMoreCallback($callbackId, $chatId, $messageId, $telegramId, $data);
            return;
        }

        if($data === 'back_to_plans'){
            $this->answerCallbackQuery($callbackId);
            $this->handleManagePlans($telegramId, $chatId);
            return;
        }

        if(str_starts_with($data, 'mp:')){
            $this->handleSelectPlanCallback($callbackId, $chatId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'rp:')){
            $this->handleRenamePlanCallback($callbackId, $chatId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'ap:')){
            $this->handleActivatePlanCallback($callbackId, $chatId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'atp:')){
            $this->handleAddTaskToPlanCallback($callbackId, $chatId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'dpc:')){
            $this->handleConfirmDeletePlanCallback($callbackId, $chatId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'dp:')){
            $this->handleDeletePlanCallback($callbackId, $chatId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'mtg:')){
            $this->handleManageTaskGroupCallback($callbackId, $chatId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'etn:')){
            $this->handleEditTaskFieldStart($callbackId, $chatId, $telegramId, $data, 'etn', 'editing_task_title', "Yangi task nomini kiriting:");
            return;
        }

        if(str_starts_with($data, 'etv:')){
            $this->handleEditTaskFieldStart($callbackId, $chatId, $telegramId, $data, 'etv', 'editing_task_value', "Yangi qiymat va birlikni yozing (masalan: 45 daqiqa):");
            return;
        }

        if(str_starts_with($data, 'etr:')){
            $this->handleEditTaskFieldStart($callbackId, $chatId, $telegramId, $data, 'etr', 'editing_task_reminder', "Yangi eslatma vaqtini kiriting (masalan 08:00), yoki olib tashlash uchun \"yo'q\" deb yozing:");
            return;
        }

        if(str_starts_with($data, 'etd:')){
            $this->handleEditTaskDaysStart($callbackId, $chatId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'dtgc:')){
            $this->handleConfirmDeleteTaskGroupCallback($callbackId, $chatId, $telegramId, $data);
            return;
        }

        if(str_starts_with($data, 'dtg:')){
            $this->handleDeleteTaskGroupCallback($callbackId, $chatId, $telegramId, $data);
            return;
        }

        $this->answerCallbackQuery($callbackId);
    }

    private function handleConfirmTaskCallback(string $callbackId, int $chatId, string $data){
        $taskId = (int) str_replace('confirm_task:', '', $data);
        $task = DailyPlanTask::find($taskId);

        if(!$task){
            $this->answerCallbackQuery($callbackId, "Task topilmadi");
            return;
        }

        $this->answerCallbackQuery($callbackId);

        if($task->is_done){
            $this->sendMessage($chatId, "✅ \"{$task->title}\" allaqachon bajarilgan.");
            return;
        }

        $this->sendMessage($chatId, "\"{$task->title}\" ni bajardingizmi?", [
            [
                ['text' => '✅ Ha', 'callback_data' => "complete_task:{$task->id}"],
                ['text' => '❌ Yo\'q', 'callback_data' => "cancel_task:{$task->id}"],
            ],
        ]);
    }

    private function handleCancelTaskCallback(string $callbackId, int $chatId, int $messageId, string $data){
        $this->answerCallbackQuery($callbackId, "Bekor qilindi");
        $this->editMessageText($chatId, $messageId, "❌ Bekor qilindi", []);
    }

    private function handleCompleteTaskCallback(string $callbackId, int $chatId, int $messageId, string $data){
        $taskId = (int) str_replace('complete_task:', '', $data);
        $task = DailyPlanTask::find($taskId);

        if(!$task){
            $this->answerCallbackQuery($callbackId, "Task topilmadi");
            return;
        }

        $wasAlreadyDone = $task->is_done;
        if(!$wasAlreadyDone){
            $this->dailyPlanService->complete($task);
        }

        $message = str_replace('{title}', $task->title, self::COMPLETION_MESSAGES[array_rand(self::COMPLETION_MESSAGES)]);
        $this->answerCallbackQuery($callbackId, $wasAlreadyDone ? "Allaqachon bajarilgan ✅" : "Bajarildi! ✅");
        $this->editMessageText($chatId, $messageId, $message, []);

        if(!$wasAlreadyDone){
            $allDone = $this->celebrateIfAllDone($task->dailyPlan);
            if(!$allDone){
                $this->sendRemainingTasksNote($task->dailyPlan);
            }
        }
    }

    private function celebrateIfAllDone(DailyPlan $dailyPlan): bool
    {
        $dailyPlan->loadMissing(['tasks', 'user']);
        $tasks = $dailyPlan->tasks;

        if($tasks->isNotEmpty() && $tasks->where('is_done', false)->isEmpty()){
            $this->sendMessage(
                $dailyPlan->user->telegram_id,
                "🎉 <b>Tabriklaymiz!</b>\n\nBugungi barcha vazifalaringizni bajardingiz! Ertaga ham shu ruhda davom eting 💪",
                null,
                'HTML'
            );
            return true;
        }

        return false;
    }

    private function sendRemainingTasksNote(DailyPlan $dailyPlan){
        $dailyPlan->loadMissing(['tasks', 'user']);
        $remaining = $dailyPlan->tasks->where('is_done', false);

        if($remaining->isEmpty()){
            return;
        }

        $list = $remaining->map(fn($task) => "• {$task->title}")->implode("\n");
        $this->sendMessage($dailyPlan->user->telegram_id, "📌 Yana shular qoldi:\n{$list}");
    }

    private function handleFriendResponseCallback(string $callbackId, int $chatId, int $messageId, string $data){
        [$action, $friendshipId] = explode(':', $data);
        $friendship = Friendship::find($friendshipId);

        if(!$friendship){
            $this->answerCallbackQuery($callbackId, "So'rov topilmadi");
            return;
        }

        if($action === 'accept_friend'){
            $this->friendshipService->accept($friendship);
            $this->answerCallbackQuery($callbackId, "Qabul qilindi");
            $this->editMessageText($chatId, $messageId, "✅ Siz endi {$friendship->user->name} bilan do'stsiz", []);
            $this->sendMessage($friendship->user->telegram_id, "{$friendship->friend->name} sizning do'stlik so'rovingizni qabul qildi!");
            return;
        }

        $this->friendshipService->decline($friendship);
        $this->answerCallbackQuery($callbackId, "Rad etildi");
        $this->editMessageText($chatId, $messageId, "❌ So'rov rad etildi", []);
    }

    private function handlePlanDurationCallback(string $callbackId, int $chatId, int $messageId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user || $user->bot_state !== 'awaiting_plan_duration'){
            $this->answerCallbackQuery($callbackId);
            return;
        }

        $value = str_replace('plan_duration:', '', $data);
        $planId = $user->bot_state_data['weekly_plan_id'] ?? null;
        $plan = WeeklyPlan::find($planId);
        if(!$plan){
            $this->answerCallbackQuery($callbackId, "Reja topilmadi");
            return;
        }

        if($value === 'none'){
            $plan->update(['duration_days' => null, 'ends_at' => null]);
            $label = 'Cheksiz (doimiy)';
        } else {
            $days = (int) $value;
            $plan->update(['duration_days' => $days, 'ends_at' => now()->addDays($days)->toDateString()]);
            $label = "{$days} kun";
        }

        $this->answerCallbackQuery($callbackId);
        $this->editMessageText($chatId, $messageId, "Muddat: {$label} ✅");

        $user->update(['bot_state' => 'awaiting_task_weekday', 'bot_state_data' => ['weekly_plan_id' => $planId, 'draft_task' => [], 'mode' => 'create']]);
        $this->sendWeekdayPrompt($chatId);
    }

    private function handleTaskWeekdayCallback(string $callbackId, int $chatId, int $messageId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user){
            $this->answerCallbackQuery($callbackId);
            return;
        }

        $value = str_replace('task_weekday:', '', $data);
        $weekdays = self::WEEKDAY_GROUPS[$value] ?? [$value];
        $label = self::WEEKDAY_GROUP_LABELS[$value] ?? (self::WEEKDAY_LABELS[$value] ?? $value);

        if($user->bot_state === 'awaiting_task_weekday'){
            $draft = $user->bot_state_data['draft_task'] ?? [];
            $draft['weekdays'] = $weekdays;
            $draft['group_key'] = $draft['group_key'] ?? Str::random(10);
            $user->update([
                'bot_state' => 'awaiting_task_title',
                'bot_state_data' => array_merge($user->bot_state_data, ['draft_task' => $draft]),
            ]);

            $this->answerCallbackQuery($callbackId);
            $this->editMessageText($chatId, $messageId, "Kun: {$label} ✅");
            $this->sendMessage($chatId, "Task nomini kiriting (masalan: Sport):");
            return;
        }

        if($user->bot_state === 'editing_task_days'){
            $groupKey = $user->bot_state_data['group_key'] ?? null;
            $this->answerCallbackQuery($callbackId);

            if(!$groupKey || !$this->groupBelongsToUser($groupKey, $user)){
                $user->update(['bot_state' => null, 'bot_state_data' => null]);
                $this->sendMessage($chatId, "Task topilmadi.");
                return;
            }

            $rep = WeeklyPlanTask::where('group_key', $groupKey)->first();
            $planId = $rep->weekly_plan_id;

            DB::transaction(function() use ($groupKey, $rep, $weekdays, $planId){
                WeeklyPlanTask::where('group_key', $groupKey)->delete();
                $plan = WeeklyPlan::find($planId);
                foreach($weekdays as $weekday){
                    $plan->tasks()->create([
                        'group_key' => $groupKey,
                        'weekday' => $weekday,
                        'title' => $rep->title,
                        'type' => $rep->type,
                        'target_value' => $rep->target_value,
                        'target_unit' => $rep->target_unit,
                        'remind_at' => $rep->remind_at,
                        'position' => $plan->tasks()->count(),
                    ]);
                }
            });

            $user->update(['bot_state' => null, 'bot_state_data' => null]);
            $this->editMessageText($chatId, $messageId, "Kun(lar): {$label} ✅");
            $this->showTaskGroupView($chatId, $groupKey);
            return;
        }

        $this->answerCallbackQuery($callbackId);
    }

    private function handleTaskTypeCallback(string $callbackId, int $chatId, int $messageId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user || $user->bot_state !== 'awaiting_task_type'){
            $this->answerCallbackQuery($callbackId);
            return;
        }

        $type = str_replace('task_type:', '', $data);
        $draft = $user->bot_state_data['draft_task'] ?? [];
        $draft['type'] = $type;
        $user->update(['bot_state_data' => array_merge($user->bot_state_data, ['draft_task' => $draft])]);

        $this->answerCallbackQuery($callbackId);
        $this->editMessageText($chatId, $messageId, "Turi: ".(self::TYPE_LABELS[$type] ?? $type)." ✅");

        if($type === TaskType::Checkbox->value){
            $this->askReminderChoice($user->fresh(), $chatId);
        } else {
            $user->update(['bot_state' => 'awaiting_task_value_unit']);
            $unitExample = $type === TaskType::Duration->value ? '30 daqiqa' : '20 sahifa';
            $this->sendMessage($chatId, "Qiymat va birlikni yozing (masalan: {$unitExample}):");
        }
    }

    private function handleTaskReminderCallback(string $callbackId, int $chatId, int $messageId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user || $user->bot_state !== 'awaiting_task_reminder_choice'){
            $this->answerCallbackQuery($callbackId);
            return;
        }

        $choice = str_replace('task_reminder:', '', $data);
        $this->answerCallbackQuery($callbackId);

        if($choice === 'no'){
            $this->editMessageText($chatId, $messageId, "Eslatma: yo'q");
            $this->saveDraftTaskAndAskMore($user, $chatId);
            return;
        }

        $this->editMessageText($chatId, $messageId, "Eslatma: vaqt kiriting ⌛");
        $user->update(['bot_state' => 'awaiting_task_reminder_time']);
        $this->sendMessage($chatId, "Eslatma vaqtini kiriting (masalan 07:00):");
    }

    private function handlePlanMoreCallback(string $callbackId, int $chatId, int $messageId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user || $user->bot_state !== 'awaiting_more_tasks'){
            $this->answerCallbackQuery($callbackId);
            return;
        }

        $choice = str_replace('plan_more:', '', $data);
        $mode = $this->currentMode($user);
        $this->answerCallbackQuery($callbackId);

        if($choice === 'yes'){
            $this->editMessageText($chatId, $messageId, "Yana task qo'shilyapti...");
            $planId = $user->bot_state_data['weekly_plan_id'] ?? null;
            $user->update(['bot_state' => 'awaiting_task_weekday', 'bot_state_data' => ['weekly_plan_id' => $planId, 'draft_task' => [], 'mode' => $mode]]);
            $this->sendWeekdayPrompt($chatId);
            return;
        }

        $this->editMessageText($chatId, $messageId, "Reja yakunlandi ✅");
        if($mode === 'add_to_existing'){
            $this->finalizeAddToExisting($user, $chatId);
        } else {
            $this->finalizePlanCreation($user, $chatId);
        }
    }

    private function handleSelectPlanCallback(string $callbackId, int $chatId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        $planId = (int) str_replace('mp:', '', $data);
        $plan = WeeklyPlan::where('id', $planId)->where('user_id', $user?->id)->first();

        $this->answerCallbackQuery($callbackId);

        if(!$plan){
            $this->sendMessage($chatId, "Reja topilmadi.");
            return;
        }

        $this->showPlanManagementView($chatId, $plan);
    }

    private function handleRenamePlanCallback(string $callbackId, int $chatId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        $planId = (int) str_replace('rp:', '', $data);
        $plan = WeeklyPlan::where('id', $planId)->where('user_id', $user?->id)->first();

        $this->answerCallbackQuery($callbackId);

        if(!$plan || !$user){
            $this->sendMessage($chatId, "Reja topilmadi.");
            return;
        }

        $user->update(['bot_state' => 'editing_plan_rename', 'bot_state_data' => ['plan_id' => $plan->id]]);
        $this->sendMessage($chatId, "Yangi reja nomini kiriting:");
    }

    private function handleActivatePlanCallback(string $callbackId, int $chatId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        $planId = (int) str_replace('ap:', '', $data);
        $plan = WeeklyPlan::where('id', $planId)->where('user_id', $user?->id)->first();

        $this->answerCallbackQuery($callbackId);

        if(!$plan || !$user){
            $this->sendMessage($chatId, "Reja topilmadi.");
            return;
        }

        $this->weeklyPlanService->activate($plan);
        $this->dailyPlanService->generateForDate($user, now($user->timezone));
        $this->sendMessage($chatId, "✅ \"{$plan->name}\" faollashtirildi.");
        $this->showPlanManagementView($chatId, $plan->fresh());
    }

    private function handleAddTaskToPlanCallback(string $callbackId, int $chatId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        $planId = (int) str_replace('atp:', '', $data);
        $plan = WeeklyPlan::where('id', $planId)->where('user_id', $user?->id)->first();

        $this->answerCallbackQuery($callbackId);

        if(!$plan || !$user){
            $this->sendMessage($chatId, "Reja topilmadi.");
            return;
        }

        $user->update(['bot_state' => 'awaiting_task_weekday', 'bot_state_data' => ['weekly_plan_id' => $plan->id, 'draft_task' => [], 'mode' => 'add_to_existing']]);
        $this->sendWeekdayPrompt($chatId);
    }

    private function handleDeletePlanCallback(string $callbackId, int $chatId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        $planId = (int) str_replace('dp:', '', $data);
        $plan = WeeklyPlan::where('id', $planId)->where('user_id', $user?->id)->first();

        $this->answerCallbackQuery($callbackId);

        if(!$plan){
            $this->sendMessage($chatId, "Reja topilmadi.");
            return;
        }

        $this->sendMessage($chatId, "\"{$plan->name}\" rostdan o'chirilsinmi? Barcha tasklar ham o'chadi.", [
            [['text' => '🗑 Ha, o\'chirish', 'callback_data' => "dpc:{$plan->id}"], ['text' => '❌ Yo\'q', 'callback_data' => "mp:{$plan->id}"]],
        ]);
    }

    private function handleConfirmDeletePlanCallback(string $callbackId, int $chatId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        $planId = (int) str_replace('dpc:', '', $data);
        $plan = WeeklyPlan::where('id', $planId)->where('user_id', $user?->id)->first();

        $this->answerCallbackQuery($callbackId);

        if(!$plan){
            $this->sendMessage($chatId, "Reja topilmadi.");
            return;
        }

        $name = $plan->name;
        DB::transaction(function() use ($plan){
            $plan->tasks()->delete();
            $plan->delete();
        });

        $this->sendMessage($chatId, "🗑 \"{$name}\" o'chirildi.");
        $this->handleManagePlans($telegramId, $chatId);
    }

    private function handleManageTaskGroupCallback(string $callbackId, int $chatId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        $groupKey = str_replace('mtg:', '', $data);

        $this->answerCallbackQuery($callbackId);

        if(!$user || !$this->groupBelongsToUser($groupKey, $user)){
            $this->sendMessage($chatId, "Task topilmadi.");
            return;
        }

        $this->showTaskGroupView($chatId, $groupKey);
    }

    private function handleEditTaskFieldStart(string $callbackId, int $chatId, ?int $telegramId, string $data, string $prefix, string $state, string $prompt){
        $user = User::where('telegram_id', $telegramId)->first();
        $groupKey = str_replace("{$prefix}:", '', $data);

        $this->answerCallbackQuery($callbackId);

        if(!$user || !$this->groupBelongsToUser($groupKey, $user)){
            $this->sendMessage($chatId, "Task topilmadi.");
            return;
        }

        $user->update(['bot_state' => $state, 'bot_state_data' => ['group_key' => $groupKey]]);
        $this->sendMessage($chatId, $prompt);
    }

    private function handleEditTaskDaysStart(string $callbackId, int $chatId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        $groupKey = str_replace('etd:', '', $data);

        $this->answerCallbackQuery($callbackId);

        if(!$user || !$this->groupBelongsToUser($groupKey, $user)){
            $this->sendMessage($chatId, "Task topilmadi.");
            return;
        }

        $user->update(['bot_state' => 'editing_task_days', 'bot_state_data' => ['group_key' => $groupKey]]);
        $this->sendWeekdayPrompt($chatId);
    }

    private function handleDeleteTaskGroupCallback(string $callbackId, int $chatId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        $groupKey = str_replace('dtg:', '', $data);

        $this->answerCallbackQuery($callbackId);

        if(!$user || !$this->groupBelongsToUser($groupKey, $user)){
            $this->sendMessage($chatId, "Task topilmadi.");
            return;
        }

        $rep = WeeklyPlanTask::where('group_key', $groupKey)->first();
        $this->sendMessage($chatId, "\"{$rep->title}\" barcha kunlardan o'chirilsinmi?", [
            [['text' => '🗑 Ha, o\'chirish', 'callback_data' => "dtgc:{$groupKey}"], ['text' => '❌ Yo\'q', 'callback_data' => "mtg:{$groupKey}"]],
        ]);
    }

    private function handleConfirmDeleteTaskGroupCallback(string $callbackId, int $chatId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        $groupKey = str_replace('dtgc:', '', $data);

        $this->answerCallbackQuery($callbackId);

        if(!$user || !$this->groupBelongsToUser($groupKey, $user)){
            $this->sendMessage($chatId, "Task topilmadi.");
            return;
        }

        $rep = WeeklyPlanTask::where('group_key', $groupKey)->first();
        $planId = $rep->weekly_plan_id;
        $title = $rep->title;

        WeeklyPlanTask::where('group_key', $groupKey)->delete();

        $this->sendMessage($chatId, "🗑 \"{$title}\" o'chirildi.");
        $plan = WeeklyPlan::find($planId);
        if($plan){
            $this->showPlanManagementView($chatId, $plan);
        }
    }

    public function sendMessage(int $chatId, string $text, ?array $inlineKeyboard = null, ?string $parseMode = null){
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];
        if($inlineKeyboard){
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }
        if($parseMode){
            $payload['parse_mode'] = $parseMode;
        }
        Http::post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage', $payload);
    }

    public function sendReminderBatch(int $chatId, $tasks){
        $lines = $tasks->values()->map(fn($task, $index) => ($index + 1).". {$task->title}")->implode("\n");
        $text = "⏰ <b>Eslatma</b>\n\nBajarilmagan tasklaringiz:\n{$lines}";

        $buttons = $tasks->map(fn($task) => [['text' => "✅ {$task->title}", 'callback_data' => "confirm_task:{$task->id}"]])->all();

        $this->sendMessage($chatId, $text, $buttons, 'HTML');
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''){
        Http::post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
        ]);
    }

    public function editMessageText(int $chatId, int $messageId, string $text, ?array $inlineKeyboard = null){
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ];
        if($inlineKeyboard !== null){
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }
        Http::post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/editMessageText', $payload);
    }
}
