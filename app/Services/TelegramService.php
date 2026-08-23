<?php

namespace App\Services;
use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
class TelegramService
{
    private const MENU_TODAY = '📋 Bugungi tasklarim';
    private const MENU_FRIENDS = '👥 Do\'stlarim';
    private const MENU_INVITE = '➕ Do\'st taklif qilish';
    private const MENU_HELP = 'ℹ️ Yordam';

    public function __construct(
        private DailyPlanService $dailyPlanService,
        private FriendshipService $friendshipService,
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
        if($command === '/help' || $text === self::MENU_HELP){
            $this->handleHelp($chatId);
            return;
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
            $this->sendMessage($chatId, "Bugun uchun task yo'q.");
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
                    [self::MENU_TODAY, self::MENU_FRIENDS],
                    [self::MENU_INVITE, self::MENU_HELP],
                ],
                'resize_keyboard' => true,
            ]),
        ];
        Http::post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage', $payload);
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
