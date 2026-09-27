<?php

// ==============================================================================
// 1. [ الإعدادات الرئيسية والمتغيرات ]
// ==============================================================================

// توكن البوت الخاص بك من BotFather
$botToken = "8405096687:AAG4EfLGwWLnuLadBlmuvGaXdRimcyV1HoQ";

// معرّف الشات (Chat ID) الخاص بك كمطور/أدمن
$adminId = 1254240396;

// بيانات API الخاصة بالموقع المزود (Provider)
$providerApiUrl = "https://nxce.io/api/v1"; 
$providerApiKey = "pk_live_h5xu...";

// روابط ودوال التليجرام العامة
$apiUrl   = "https://api.telegram.org/bot" . $botToken;
$dataFile = "database.json";

// ==============================================================================
// 2. [ إدارة قاعدة البيانات المحلية JSON ]
// ==============================================================================

function loadData($file) {
    if (!file_exists($file)) {
        $initialData = [
            'users' => [], // قائمة الزبائن والأرصدة [chat_id => balance]
            'prices' => [
                'local_daily'    => 1.0,  'local_weekly'   => 5.33,  'local_monthly'   => 10.0,
                'global_daily'   => 1.33, 'global_weekly'  => 6.66,  'global_monthly'  => 16.66,
                'bolt_daily'     => 3.0,  'bolt_weekly'    => 10.0,  'bolt_monthly'   => 20.0
            ],
            // معرّفات الخدمات (Service ID) في لوحة الموقع المزود
            'service_ids' => [
                'local_daily'    => 'REF-1772349242374', 'local_weekly'   => 102, 'local_monthly'   => 103,
                'global_daily'   => 201, 'global_weekly'  => 202, 'global_monthly'  => 203,
                'bolt_daily'     => 301, 'bolt_weekly'    => 302, 'bolt_monthly'   => 303
            ]
        ];
        file_put_contents($file, json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $initialData;
    }
    return json_decode(file_get_contents($file), true);
}

function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$db = loadData($dataFile);

// أسماء المنتجات للعرض والنسخ
$productNames = [
    'local_daily'    => 'محلي - يومي',
    'local_weekly'   => 'محلي - أسبوعي',
    'local_monthly'  => 'محلي - شهري',
    'global_daily'   => 'عالمي - يومي',
    'global_weekly'  => 'عالمي - أسبوعي',
    'global_monthly' => 'عالمي - شهري',
    'bolt_daily'     => 'بولت تراك - يومي',
    'bolt_weekly'    => 'بولت تراك - أسبوعي',
    'bolt_monthly'   => 'بولت تراك - شهري'
];

// ==============================================================================
// 3. [ استقبال ومعالجة المدخلات من تليجرام ]
// ==============================================================================

$content = file_get_contents("php://input");
$update  = json_decode($content, true);

// ------------------- [ أولاً: الرسائل النصية ] -------------------
if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $text   = trim($update["message"]["text"] ?? '');

    // تسجيل الزبون التلقائي فور دخوله البوت أول مرة
    if (!isset($db['users'][$chatId])) {
        $db['users'][$chatId] = 0.0;
        saveData($dataFile, $db);
    }

    // أمر البدء
    if ($text === "/start") {
        sendMainMenu($chatId, $apiUrl, $db['users'][$chatId]);
        exit;
    }

    // --- [ أوامر المطور / الأدمن ] ---

    // 1. عرض جميع الزبائن المسجلين وأرصدتهم (/users)
    if ($chatId == $adminId && $text === "/users") {
        $totalUsers   = count($db['users']);
        $totalBalance = array_sum($db['users']);

        $msg  = "👥 **قائمة الزبائن المسجلين في البوت**\n";
        $msg .= "ــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــ\n\n";

        $i = 1;
        foreach ($db['users'] as $uId => $balance) {
            $msg .= "{$i}. 👤 **ID:** `{$uId}`\n";
            $msg .= "   💳 **الرصيد:** `{$balance} USDT`\n";
            $msg .= "----------------------------------\n";
            $i++;

            if (strlen($msg) > 3500) {
                sendMessage($chatId, $msg, null, $apiUrl);
                $msg = "";
            }
        }

        $msg .= "\n📊 **الملخص العام:**\n";
        $msg .= "▫️ **إجمالي الزبائن:** `{$totalUsers}`\n";
        $msg .= "▫️ **إجمالي أموال المحافظ:** `{$totalBalance} USDT`\n";
        $msg .= "ــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــ";

        sendMessage($chatId, $msg, null, $apiUrl);
        exit;
    }

    // 2. شحن رصيد لزبون (/addbalance USER_ID AMOUNT)
    if ($chatId == $adminId && strpos($text, "/addbalance") === 0) {
        $parts = explode(" ", $text);
        if (count($parts) === 3 && is_numeric($parts[2])) {
            $targetUser = $parts[1];
            $amount     = (float)$parts[2];

            if (!isset($db['users'][$targetUser])) {
                $db['users'][$targetUser] = 0.0;
            }

            $db['users'][$targetUser] += $amount;
            saveData($dataFile, $db);

            sendMessage($chatId, "✅ تم شحن `{$amount} USDT` للزبون `{$targetUser}`.\nالرصيد الجديد: `{$db['users'][$targetUser]} USDT`", null, $apiUrl);
            sendMessage($targetUser, "🎉 **تم شحن رصيدك بنجاح!**\nتم إضافة `{$amount} USDT` إلى محفظتك بالبوت.\nرصيدك الحالي: `{$db['users'][$targetUser]} USDT`", null, $apiUrl);
        } else {
            sendMessage($chatId, "⚠️ الصيغة خاطئة، استخدم:\n`/addbalance 123456789 50`", null, $apiUrl);
        }
        exit;
    }

    // 3. خصم رصيد من زبون (/subbalance USER_ID AMOUNT)
    if ($chatId == $adminId && strpos($text, "/subbalance") === 0) {
        $parts = explode(" ", $text);
        if (count($parts) === 3 && is_numeric($parts[2])) {
            $targetUser = $parts[1];
            $amount     = (float)$parts[2];

            if (isset($db['users'][$targetUser])) {
                $db['users'][$targetUser] = max(0, $db['users'][$targetUser] - $amount);
                saveData($dataFile, $db);

                sendMessage($chatId, "✅ تم خصم `{$amount} USDT` من الزبون `{$targetUser}`.\nالرصيد الحالي: `{$db['users'][$targetUser]} USDT`", null, $apiUrl);
                sendMessage($targetUser, "⚠️ **تعديل رصيد:**\nتم خصم `{$amount} USDT` من محفظتك.\nرصيدك الحالي: `{$db['users'][$targetUser]} USDT`", null, $apiUrl);
            } else {
                sendMessage($chatId, "❌ الزبون غير مسجل في البوت.", null, $apiUrl);
            }
        } else {
            sendMessage($chatId, "⚠️ الصيغة خاطئة، استخدم:\n`/subbalance 123456789 10`", null, $apiUrl);
        }
        exit;
    }
}

// ------------------- [ ثانياً: ضغطات الأزرار الشفافة ] -------------------
if (isset($update["callback_query"])) {
    $cb          = $update["callback_query"];
    $chatId      = $cb["message"]["chat"]["id"];
    $msgId       = $cb["message"]["message_id"];
    $data        = $cb["data"];
    $userBalance = $db['users'][$chatId] ?? 0.0;

    if ($data === "main_menu") {
        editMainMenu($chatId, $msgId, $apiUrl, $userBalance);
    } elseif (in_array($data, ['cat_local', 'cat_global', 'cat_bolt'])) {
        showCategoryProducts($chatId, $msgId, $data, $apiUrl, $db, $productNames);
    } elseif (strpos($data, "buy_") === 0) {
        $pCode = str_replace("buy_", "", $data);
        processOrderViaAPI($chatId, $msgId, $pCode, $apiUrl, $db, $productNames, $dataFile, $providerApiUrl, $providerApiKey);
    }
}

// ==============================================================================
// 4. [ منطق الشراء والربط المباشر مع API الموقع المزود ]
// ==============================================================================

function processOrderViaAPI($chatId, $msgId, $pCode, $apiUrl, &$db, $productNames, $dataFile, $providerApiUrl, $providerApiKey) {
    $price       = $db['prices'][$pCode] ?? 0;
    $userBalance = $db['users'][$chatId] ?? 0.0;

    // 1. فحص رصيد الزبون في محفظة البوت
    if ($userBalance < $price) {
        $msg  = "❌ **رصيدك غير كافٍ لإتمام عملية الشراء!**\n\n";
        $msg .= "📌 **سعر الخدمة:** `{$price} USDT`\n";
        $msg .= "💳 **رصيدك الحالي:** `{$userBalance} USDT`\n\n";
        $msg .= "يرجى الشحن من المطور لاستكمال طلبك.";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '💬 تواصل مع المطور للشحن', 'url' => 'https://t.me/Y_a_J2003']],
                [['text' => '🔙 العودة للقائمة', 'callback_data' => 'main_menu']]
            ]
        ];
        editMessageText($chatId, $msgId, $msg, $keyboard, $apiUrl);
        return;
    }

    // 2. إرسال الطلب عبر cURL إلى API الموقع المزود
    $serviceId = $db['service_ids'][$pCode];

    $apiPayload = [
        'key'      => $providerApiKey,
        'action'   => 'add',
        'service'  => $serviceId,
        'quantity' => 1
    ];

    $ch = curl_init($providerApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiPayload));
    $responseRaw = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($responseRaw, true);

    // 3. معالجة نتيجة الشراء
    if ($response && (isset($response['order']) || (isset($response['status']) && $response['status'] === 'success'))) {
        
        // خصم السعر من رصيد محفظة الزبون في البوت
        $db['users'][$chatId] -= $price;
        saveData($dataFile, $db);

        $orderId      = $response['order'] ?? rand(100000, 999999);
        $itemUsername = $response['username'] ?? ($response['code'] ?? 'تم التفعيل بنجاح');
        $itemPassword = $response['password'] ?? 'راجِع التفاصيل في الحساب';
        $purchaseDate = date("Y-m-d H:i");

        // بناء رسالة الفاتورة الاحترافية والتسليم
        $msg  = "🎉 **تمت عملية الشراء بنجاح!**\n";
        $msg .= "شكراً لتسوقك من **متجر الفانتوم** 👻\n";
        $msg .= "ــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــ\n\n";

        $msg .= "🧾 **تفاصيل الفاتورة:**\n";
        $msg .= "▫️ **رقم الطلب:** `#{$orderId}`\n";
        $msg .= "▫️ **الخدمة:** {$productNames[$pCode]}\n";
        $msg .= "▫️ **تاريخ ووقت الشراء:** `{$purchaseDate}`\n";
        $msg .= "▫️ **المبلغ المخصوم:** `{$price} USDT`\n\n";

        $msg .= "ــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــ\n\n";

        $msg .= "🔑 **بيانات الاشتراك الخاصة بك:**\n";
        $msg .= "👤 **اليوزر / الكود:** `{$itemUsername}`\n";
        $msg .= "🔑 **كلمة المرور:** `{$itemPassword}`\n\n";
        $msg .= "💡 *اضغط على البيانات أعلاه لنسخها بنقرة واحدة.*\n\n";

        $msg .= "ــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــ\n\n";

        $msg .= "💳 **حالة المحفظة:**\n";
        $msg .= "▫️ **الرصيد المتبقي:** `{$db['users'][$chatId]} USDT`\n\n";

        $msg .= "ــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــ\n";
        $msg .= "⚙️ لأي استفسار، تواصل مع الدعم الفني: @Y_a_J2003";

        editMessageText($chatId, $msgId, $msg, null, $apiUrl);

    } else {
        // فشل الشراء من الموقع المزود
        $msg  = "⚠️ **عذراً، تعذر إتمام الطلب آلياً الآن**\n\n";
        $msg .= "لم يتم خصم أي مبلغ من رصيدك بالبوت.\n";
        $msg .= "يرجى التواصل مع المطور للتفعيل المباشر.\n\n";
        $msg .= "👨‍💻 **حساب المطور:** @Y_a_J2003";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '💬 تواصل مع المطور', 'url' => 'https://t.me/Y_a_J2003']],
                [['text' => '🔙 العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']]
            ]
        ];
        editMessageText($chatId, $msgId, $msg, $keyboard, $apiUrl);
    }
}

// ==============================================================================
// 5. [ واجهات الواجهة والأزرار والتواصل ]
// ==============================================================================

function sendMainMenu($chatId, $apiUrl, $balance) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🏷️ خدمات المحلي', 'callback_data' => 'cat_local']],
            [['text' => '🌐 خدمات العالمي', 'callback_data' => 'cat_global']],
            [['text' => '⚡ خدمات بولت تراك', 'callback_data' => 'cat_bolt']],
            [['text' => '💬 الدعم الفني / الشحن', 'url' => 'https://t.me/Y_a_J2003']]
        ]
    ];
    $msg = "👻 **أهلاً بك في متجر الفانتوم**\n\n💳 **رصيدك الحالي:** `{$balance} USDT`\n\nاختر القسم المطلوب لتصفح الاشتراكات:";
    sendMessage($chatId, $msg, $keyboard, $apiUrl);
}

function editMainMenu($chatId, $msgId, $apiUrl, $balance) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🏷️ خدمات المحلي', 'callback_data' => 'cat_local']],
            [['text' => '🌐 خدمات العالمي', 'callback_data' => 'cat_global']],
            [['text' => '⚡ خدمات بولت تراك', 'callback_data' => 'cat_bolt']],
            [['text' => '💬 الدعم الفني / الشحن', 'url' => 'https://t.me/Y_a_J2003']]
        ]
    ];
    $msg = "👻 **أهلاً بك في متجر الفانتوم**\n\n💳 **رصيدك الحالي:** `{$balance} USDT`\n\nاختر القسم المطلوب لتصفح الاشتراكات:";
    editMessageText($chatId, $msgId, $msg, $keyboard, $apiUrl);
}

function showCategoryProducts($chatId, $msgId, $catKey, $apiUrl, $db, $productNames) {
    $categories = [
        'cat_local'  => ['title' => "🏷️ **خدمات المحلي**", 'keys' => ['local_daily', 'local_weekly', 'local_monthly']],
        'cat_global' => ['title' => "🌐 **خدمات العالمي**", 'keys' => ['global_daily', 'global_weekly', 'global_monthly']],
        'cat_bolt'   => ['title' => "⚡ **خدمات بولت تراك**", 'keys' => ['bolt_daily', 'bolt_weekly', 'bolt_monthly']],
    ];

    $cat = $categories[$catKey];
    $keyboard = ['inline_keyboard' => []];

    foreach ($cat['keys'] as $pCode) {
        $price = $db['prices'][$pCode] ?? 0;
        $keyboard['inline_keyboard'][] = [
            ['text' => $productNames[$pCode] . " - (" . $price . " USDT)", 'callback_data' => 'buy_' . $pCode]
        ];
    }
    $keyboard['inline_keyboard'][] = [['text' => '🔙 العودة للقائمة الرئيسية', 'callback_data' => 'main_menu']];

    editMessageText($chatId, $msgId, $cat['title'] . "\n\nاختر مدة الاشتراك المناسبة لك:", $keyboard, $apiUrl);
}

// ==============================================================================
// 6. [ الدوال العامة للربط مع API تليجرام عبر cURL ]
// ==============================================================================

function sendMessage($chatId, $text, $keyboard, $apiUrl) {
    $postData = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];
    if ($keyboard) $postData['reply_markup'] = json_encode($keyboard);
    sendCurl($apiUrl . '/sendMessage', $postData);
}

function editMessageText($chatId, $msgId, $text, $keyboard, $apiUrl) {
    $postData = [
        'chat_id'    => $chatId,
        'message_id' => $msgId,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];
    if ($keyboard) $postData['reply_markup'] = json_encode($keyboard);
    sendCurl($apiUrl . '/editMessageText', $postData);
}

function sendCurl($url, $postData) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_exec($ch);
    curl_close($ch);
}

?>
