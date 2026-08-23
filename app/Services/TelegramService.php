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

    private const WEEKDAYS = [
        'dushanba' => 'monday',
        'seshanba' => 'tuesday',
        'chorshanba' => 'wednesday',
        'payshanba' => 'thursday',
        'juma' => 'friday',
        'shanba' => 'saturday',
        'yakshanba' => 'sunday',
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
            $this->handlePlanCreationInput($user, $chatId, $text);
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

    private function handleCreatePlanStart(int $telegramId, int $chatId){
        $user = User::where('telegram_id', $telegramId)->first();
        if(!$user){
            $this->sendMessage($chatId, "Avval /start bosing.");
            return;
        }

        $user->update(['bot_state' => 'awaiting_plan_name', 'bot_state_data' => null]);
        $this->sendMessage($chatId, "Yangi reja nomini kiriting (masalan: Asosiy reja):");
    }

    private function handlePlanCreationInput(User $user, int $chatId, string $text){
        if($user->bot_state === 'awaiting_plan_name'){
            $plan = $this->weeklyPlanService->create($user, $text);
            $user->update([
                'bot_state' => 'awaiting_task',
                'bot_state_data' => ['weekly_plan_id' => $plan->id],
            ]);

            $kunlar = implode(', ', array_keys(self::WEEKDAYS));
            $turlar = implode(', ', array_map(fn($c) => $c->value, TaskType::cases()));

            $this->sendMessage($chatId, "\"{$plan->name}\" yaratildi. Endi tasklar qo'shing, har birini shu formatda yuboring:\n\n"
                ."kun | nomi | turi | qiymat | birlik | eslatma_vaqti\n\n"
                ."Masalan:\n"
                ."dushanba | Sport | duration | 30 | daqiqa | 07:00\n"
                ."seshanba | Kitob o'qish | count | 20 | sahifa |\n\n"
                ."Kunlar: {$kunlar}\n"
                ."Turlar: {$turlar}\n"
                ."Qiymat, birlik, eslatma — ixtiyoriy, bo'sh qoldirishingiz mumkin.\n\n"
                ."Tugatgach \"tugadi\" deb yozing, bekor qilish uchun \"bekor qilish\" deb yozing.");
            return;
        }

        if($user->bot_state === 'awaiting_task'){
            $normalized = mb_strtolower(trim($text));

            if($normalized === 'bekor qilish'){
                $planId = $user->bot_state_data['weekly_plan_id'] ?? null;
                WeeklyPlan::where('id', $planId)->delete();
                $user->update(['bot_state' => null, 'bot_state_data' => null]);
                $this->sendMessage($chatId, "Reja yaratish bekor qilindi.");
                return;
            }

            if($normalized === 'tugadi'){
                $this->finalizePlanCreation($user, $chatId);
                return;
            }

            $planId = $user->bot_state_data['weekly_plan_id'] ?? null;
            $plan = WeeklyPlan::find($planId);

            if(!$plan){
                $user->update(['bot_state' => null, 'bot_state_data' => null]);
                $this->sendMessage($chatId, "Xatolik yuz berdi, reja topilmadi. Qaytadan \"".self::MENU_CREATE_PLAN."\" bilan boshlang.");
                return;
            }

            $parsed = $this->parseTaskLine($text);

            if(is_string($parsed)){
                $this->sendMessage($chatId, $parsed);
                return;
            }

            $parsed['position'] = $plan->tasks()->count();
            $this->weeklyPlanService->addTask($plan, $parsed);
            $this->sendMessage($chatId, "✅ Qo'shildi: {$parsed['title']} ({$parsed['weekday']})");
        }
    }

    private function finalizePlanCreation(User $user, int $chatId){
        $planId = $user->bot_state_data['weekly_plan_id'] ?? null;
        $plan = WeeklyPlan::find($planId);

        if($plan && $plan->tasks()->count() > 0){
            $this->weeklyPlanService->activate($plan);
            $this->dailyPlanService->generateForDate($user, now($user->timezone));
            $taskCount = $plan->tasks()->count();
            $this->sendMessage($chatId, "✅ \"{$plan->name}\" rejasi faollashtirildi ({$taskCount} ta task). \"".self::MENU_TODAY."\" tugmasidan bugungi tasklaringizni ko'rishingiz mumkin.");
        } else {
            $plan?->delete();
            $this->sendMessage($chatId, "Hech qanday task qo'shilmadi, reja bekor qilindi.");
        }

        $user->update(['bot_state' => null, 'bot_state_data' => null]);
    }

    private function parseTaskLine(string $line): array|string
    {
        $parts = array_map('trim', explode('|', $line));

        if(count($parts) < 3){
            return "Format noto'g'ri. Kamida: kun | nomi | turi ko'rsating.";
        }

        $kun = mb_strtolower($parts[0] ?? '');
        $title = $parts[1] ?? '';
        $type = $parts[2] ?? '';
        $qiymat = $parts[3] ?? null;
        $birlik = $parts[4] ?? null;
        $eslatma = $parts[5] ?? null;

        $weekday = self::WEEKDAYS[$kun] ?? null;
        if(!$weekday){
            return "Kun nomi noto'g'ri: \"{$kun}\". Quyidagilardan birini yozing: ".implode(', ', array_keys(self::WEEKDAYS));
        }

        if($title === ''){
            return "Task nomini kiriting.";
        }

        $validTypes = array_map(fn($c) => $c->value, TaskType::cases());
        if(!in_array($type, $validTypes)){
            return "Turi noto'g'ri: \"{$type}\". Quyidagilardan birini yozing: ".implode(', ', $validTypes);
        }

        if($eslatma !== null && $eslatma !== '' && !preg_match('/^\d{1,2}:\d{2}$/', $eslatma)){
            return "Eslatma vaqti H:i formatida bo'lishi kerak (masalan 07:00).";
        }

        return [
            'weekday' => $weekday,
            'title' => $title,
            'type' => $type,
            'target_value' => ($qiymat !== null && $qiymat !== '') ? (int) $qiymat : null,
            'target_unit' => ($birlik !== null && $birlik !== '') ? $birlik : null,
            'remind_at' => ($eslatma !== null && $eslatma !== '') ? $eslatma : null,
        ];
    }

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

        if(str_starts_with($data, 'complete_task:')){
            $this->handleCompleteTaskCallback($callbackId, $chatId, $messageId, $data);
            return;
        }

        if(str_starts_with($data, 'accept_friend:') || str_starts_with($data, 'decline_friend:')){
            $this->handleFriendResponseCallback($callbackId, $chatId, $messageId, $data);
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
