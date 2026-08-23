<?php

namespace App\Services;
use App\Enums\TaskType;
use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\Friendship;
use App\Models\User;
use App\Models\WeeklyPlan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
class TelegramService
{
    private const MENU_TODAY = '📋 Bugungi tasklarim';
    private const MENU_FRIENDS = '👥 Do\'stlarim';
    private const MENU_INVITE = '➕ Do\'st taklif qilish';
    private const MENU_CREATE_PLAN = '🗓 Reja yaratish';
    private const MENU_HELP = 'ℹ️ Yordam';

    private const WEEKDAY_BUTTONS = [
        ['monday', 'Dushanba'], ['tuesday', 'Seshanba'], ['wednesday', 'Chorshanba'],
        ['thursday', 'Payshanba'], ['friday', 'Juma'], ['saturday', 'Shanba'], ['sunday', 'Yakshanba'],
    ];

    private const WEEKDAY_LABELS = [
        'monday' => 'Dushanba', 'tuesday' => 'Seshanba', 'wednesday' => 'Chorshanba',
        'thursday' => 'Payshanba', 'friday' => 'Juma', 'saturday' => 'Shanba', 'sunday' => 'Yakshanba',
    ];

    private const TYPE_LABELS = [
        'checkbox' => 'Oddiy (belgilash)', 'duration' => 'Vaqt (daqiqa/soat)', 'count' => 'Miqdor (sahifa/marta)',
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
            return [['text' => $label, 'callback_data' => "complete_task:{$task->id}"]];
        })->all();

        $this->sendMessage($chatId, "Bugungi tasklaringiz (bajarish uchun bosing):", $buttons);
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
                    [self::MENU_FRIENDS, self::MENU_INVITE],
                    [self::MENU_HELP],
                ],
                'resize_keyboard' => true,
            ]),
        ];
        Http::post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage', $payload);
    }

    // ---------- Plan creation wizard ----------

    private function handleCreatePlanStart(int $telegramId, int $chatId){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user){
            $this->sendMessage($chatId, "Avval /start bosing.");
            return;
        }

        $user->update(['bot_state' => 'awaiting_plan_name', 'bot_state_data' => null]);
        $this->sendMessage($chatId, "Yangi reja nomini kiriting (masalan: Sport rejasi):");
    }

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
            default => null,
        };
    }

    private function cancelWizard(User $user, int $chatId){
        $planId = $user->bot_state_data['weekly_plan_id'] ?? null;
        if($planId){
            WeeklyPlan::where('id', $planId)->delete();
        }
        $user->update(['bot_state' => null, 'bot_state_data' => null]);
        $this->sendMessage($chatId, "Bekor qilindi.");
    }

    private function stepPlanName(User $user, int $chatId, string $text){
        $plan = $this->weeklyPlanService->create($user, $text);
        $user->update([
            'bot_state' => 'awaiting_plan_duration',
            'bot_state_data' => ['weekly_plan_id' => $plan->id],
        ]);

        $this->sendMessage($chatId, "\"{$plan->name}\" — qancha muddatga mo'ljallangan?", [
            [['text' => '7 kun', 'callback_data' => 'plan_duration:7'], ['text' => '14 kun', 'callback_data' => 'plan_duration:14']],
            [['text' => '30 kun', 'callback_data' => 'plan_duration:30'], ['text' => '90 kun', 'callback_data' => 'plan_duration:90']],
            [['text' => '♾ Cheksiz (doimiy)', 'callback_data' => 'plan_duration:none']],
        ]);
    }

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
        $rows = collect(self::WEEKDAY_BUTTONS)
            ->map(fn($pair) => ['text' => $pair[1], 'callback_data' => "task_weekday:{$pair[0]}"])
            ->chunk(2)
            ->map(fn($chunk) => $chunk->values()->all())
            ->values()
            ->all();

        $this->sendMessage($chatId, "Task uchun qaysi kun?", $rows);
    }

    private function saveDraftTaskAndAskMore(User $user, int $chatId){
        $planId = $user->bot_state_data['weekly_plan_id'] ?? null;
        $plan = WeeklyPlan::find($planId);
        $draft = $user->bot_state_data['draft_task'] ?? [];

        if(!$plan || empty($draft['weekday']) || empty($draft['title']) || empty($draft['type'])){
            $user->update(['bot_state' => null, 'bot_state_data' => null]);
            $this->sendMessage($chatId, "Xatolik yuz berdi. Qaytadan \"".self::MENU_CREATE_PLAN."\" bilan boshlang.");
            return;
        }

        $draft['position'] = $plan->tasks()->count();
        $this->weeklyPlanService->addTask($plan, $draft);

        $weekdayLabel = self::WEEKDAY_LABELS[$draft['weekday']] ?? $draft['weekday'];
        $user->update([
            'bot_state' => 'awaiting_more_tasks',
            'bot_state_data' => ['weekly_plan_id' => $planId],
        ]);

        $this->sendMessage($chatId, "✅ Qo'shildi: {$draft['title']} ({$weekdayLabel})");
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
            $duration = $plan->duration_days ? "{$plan->duration_days} kunlik" : "doimiy";
            $this->sendMessage($chatId, "✅ \"{$plan->name}\" rejasi ({$duration}, {$taskCount} ta task) faollashtirildi. \"".self::MENU_TODAY."\" tugmasidan bugungi tasklaringizni ko'rishingiz mumkin.");
        } else {
            $plan?->delete();
            $this->sendMessage($chatId, "Hech qanday task qo'shilmadi, reja bekor qilindi.");
        }

        $user->update(['bot_state' => null, 'bot_state_data' => null]);
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

        $this->answerCallbackQuery($callbackId);
    }

    private function handleCompleteTaskCallback(string $callbackId, int $chatId, int $messageId, string $data){
        $taskId = (int) str_replace('complete_task:', '', $data);
        $task = DailyPlanTask::find($taskId);

        if(!$task){
            $this->answerCallbackQuery($callbackId, "Task topilmadi");
            return;
        }

        if(!$task->is_done){
            $this->dailyPlanService->complete($task);
        }

        $this->answerCallbackQuery($callbackId, "Bajarildi deb belgilandi ✅");
        $this->editMessageText($chatId, $messageId, "✅ {$task->title} — bajarildi");
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
            $this->editMessageText($chatId, $messageId, "✅ Siz endi {$friendship->user->name} bilan do'stsiz");
            $this->sendMessage($friendship->user->telegram_id, "{$friendship->friend->name} sizning do'stlik so'rovingizni qabul qildi!");
            return;
        }

        $this->friendshipService->decline($friendship);
        $this->answerCallbackQuery($callbackId, "Rad etildi");
        $this->editMessageText($chatId, $messageId, "❌ So'rov rad etildi");
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

        $user->update(['bot_state' => 'awaiting_task_weekday', 'bot_state_data' => ['weekly_plan_id' => $planId, 'draft_task' => []]]);
        $this->sendWeekdayPrompt($chatId);
    }

    private function handleTaskWeekdayCallback(string $callbackId, int $chatId, int $messageId, ?int $telegramId, string $data){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user || $user->bot_state !== 'awaiting_task_weekday'){
            $this->answerCallbackQuery($callbackId);
            return;
        }

        $weekday = str_replace('task_weekday:', '', $data);
        $draft = $user->bot_state_data['draft_task'] ?? [];
        $draft['weekday'] = $weekday;
        $user->update([
            'bot_state' => 'awaiting_task_title',
            'bot_state_data' => array_merge($user->bot_state_data, ['draft_task' => $draft]),
        ]);

        $this->answerCallbackQuery($callbackId);
        $this->editMessageText($chatId, $messageId, "Kun: ".(self::WEEKDAY_LABELS[$weekday] ?? $weekday)." ✅");
        $this->sendMessage($chatId, "Task nomini kiriting (masalan: Sport):");
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
        $this->answerCallbackQuery($callbackId);

        if($choice === 'yes'){
            $this->editMessageText($chatId, $messageId, "Yana task qo'shilyapti...");
            $planId = $user->bot_state_data['weekly_plan_id'] ?? null;
            $user->update(['bot_state' => 'awaiting_task_weekday', 'bot_state_data' => ['weekly_plan_id' => $planId, 'draft_task' => []]]);
            $this->sendWeekdayPrompt($chatId);
            return;
        }

        $this->editMessageText($chatId, $messageId, "Reja yakunlandi ✅");
        $this->finalizePlanCreation($user, $chatId);
    }

    public function sendMessage(int $chatId, string $text, ?array $inlineKeyboard = null){
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];
        if($inlineKeyboard){
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }
        Http::post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage', $payload);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''){
        Http::post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
        ]);
    }

    public function editMessageText(int $chatId, int $messageId, string $text){
        Http::post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ]);
    }
}
