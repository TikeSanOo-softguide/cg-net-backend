<?php

namespace Database\Seeders;

use App\Enums\NewsStatus;
use App\Models\News;
use Illuminate\Database\Seeder;

class NewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "News seeder started\n";

        collect([
            [
                'category_id' => 1,
                'title_en' => 'Our Mobile App Is Now Available',
                'title_my' => 'Mobile App ကို အသုံးပြုနိုင်ပါပြီ',
                'title_zh' => '我们的移动应用现已推出',

                'description_en' => <<<'TEXT'
                Our Mobile Application is now available, making it easier and more convenient for you to access our Internet and digital services anytime and anywhere.

                With our mobile application, you can easily check your account information, manage your Internet services, view available packages, purchase data cards, and access other useful services from your mobile phone.

                The application is designed to provide a simple and convenient experience for our customers. Instead of visiting a service center or making a phone call for basic service information, you can manage many of your services directly through the application.

                You can also browse available Internet packages and data card options, select the package that best suits your needs, and complete your purchase conveniently through the application.

                Download and use our Mobile Application today to enjoy easier access to our services and manage your Internet account whenever you need it.
                TEXT
                ,
                'description_my' => <<<'TEXT'
                ကျွန်ုပ်တို့၏ Mobile Application ကို ယခုအခါ အသုံးပြုနိုင်ပြီဖြစ်ကြောင်း ဝမ်းမြောက်စွာ အသိပေးအပ်ပါသည်။ လူကြီးမင်းတို့၏ Internet နှင့် Digital ဝန်ဆောင်မှုများကို အချိန်မရွေး၊ နေရာမရွေး ပိုမိုလွယ်ကူအဆင်ပြေစွာ အသုံးပြုနိုင်ရန်အတွက် Mobile Application ကို ဖန်တီးပေးထားခြင်းဖြစ်ပါသည်။

                Mobile Application မှတစ်ဆင့် မိမိ၏ Account အချက်အလက်များကို စစ်ဆေးခြင်း၊ Internet ဝန်ဆောင်မှုများကို စီမံခြင်း၊ ရရှိနိုင်သော Internet Package များကို ကြည့်ရှုခြင်း၊ Data Card များ ဝယ်ယူခြင်းနှင့် အခြားအသုံးဝင်သော ဝန်ဆောင်မှုများကို မိမိ၏ Mobile Phone မှတစ်ဆင့် လွယ်ကူစွာ အသုံးပြုနိုင်ပါသည်။

                အခြေခံဝန်ဆောင်မှုအချက်အလက်များ စစ်ဆေးရန် Service Center သို့ သွားရောက်ခြင်း သို့မဟုတ် ဖုန်းဆက်မေးမြန်းခြင်းများ ပြုလုပ်စရာမလိုဘဲ Application မှတစ်ဆင့် လိုအပ်သော ဝန်ဆောင်မှုများကို ပိုမိုလွယ်ကူစွာ စီမံနိုင်ပါသည်။

                ထို့အပြင် မိမိအသုံးပြုလိုသော Internet Package နှင့် Data Card များကို လွယ်ကူစွာ ရွေးချယ်ကြည့်ရှုနိုင်ပြီး မိမိ၏လိုအပ်ချက်နှင့် ကိုက်ညီသော Package ကို ရွေးချယ်ဝယ်ယူနိုင်ပါသည်။

                ကျွန်ုပ်တို့၏ Mobile Application ကို ယနေ့ပင် Download ပြုလုပ်အသုံးပြုပြီး Internet ဝန်ဆောင်မှုများကို အချိန်မရွေး၊ နေရာမရွေး ပိုမိုလွယ်ကူစွာ စီမံအသုံးပြုလိုက်ပါ။
                TEXT
                ,
                'description_zh' => <<<'TEXT'
                我们很高兴地宣布，移动应用现已正式推出，为客户提供更加便捷的互联网及数字服务体验。

                通过我们的移动应用，您可以随时随地查看账户信息、管理互联网服务、浏览可用套餐、购买数据卡以及使用其他实用功能。

                该应用旨在为客户提供简单、快捷和方便的服务体验。对于一些基本的服务查询和管理，您无需前往服务中心或拨打电话，只需通过手机应用即可轻松完成。

                您还可以通过应用浏览各种互联网套餐和数据卡，选择符合自己需求的套餐，并方便地完成购买。

                立即下载并使用我们的移动应用，随时随地轻松管理您的互联网账户，享受更加便捷的服务体验。
                TEXT
                ,
                'image_url' => 'seeder_images/news/intro_mobile_app.png',
                'status' => NewsStatus::Published,
                'slug' => 'mobile-app-now-available',
            ],
            [
                'category_id' => 1,
                'title_en' => 'Enjoy CG-NET Wi-Fi for Free',
                'title_my' => 'CG-NET Wi-Fi ကို အခမဲ့ အသုံးပြုနိုင်ပါပြီ',
                'title_zh' => '免费享受 CG-NET Wi-Fi',

                'description_en' => <<<'TEXT'
                We are pleased to introduce a special benefit for our valued customers who have been using our Internet service for 6 months or more.

                Eligible customers can now enjoy CG-NET Wi-Fi for free and experience a more convenient way to stay connected. CG-NET Wi-Fi provides an additional way to access the Internet while you are at home, at work, or in other supported locations.

                With CG-NET Wi-Fi, you can stay connected for your daily activities such as browsing the Internet, communicating with family and friends, studying, working, and enjoying online entertainment.

                This special benefit is our way of providing additional value and convenience to our long-term customers.

                If you have been using our Internet service for 6 months or more, check your eligibility and enjoy the benefits of CG-NET Wi-Fi.

                Scan the QR code or contact us to learn more about CG-NET Wi-Fi and how to enjoy this special benefit.
                TEXT
                ,
                'description_my' => <<<'TEXT'
                ကျွန်ုပ်တို့၏ Internet ဝန်ဆောင်မှုကို ၆ လနှင့်အထက် အသုံးပြုလာသော လူကြီးမင်းတို့အတွက် အထူးအခွင့်အရေးအနေဖြင့် CG-NET Wi-Fi ကို အခမဲ့ အသုံးပြုနိုင်ပြီဖြစ်ကြောင်း ဝမ်းမြောက်စွာ အသိပေးအပ်ပါသည်။

                အရည်အချင်းပြည့်မီသော Customer များအနေဖြင့် CG-NET Wi-Fi ကို အခမဲ့ အသုံးပြုနိုင်ပြီး Internet ချိတ်ဆက်အသုံးပြုရာတွင် ပိုမိုအဆင်ပြေစေရန် အထောက်အကူပြုနိုင်ပါသည်။ CG-NET Wi-Fi မှတစ်ဆင့် သတ်မှတ်ထားသော နေရာများတွင် Internet ကို ပိုမိုလွယ်ကူစွာ အသုံးပြုနိုင်ပါသည်။

                Internet အသုံးပြုခြင်း၊ မိသားစုနှင့် မိတ်ဆွေများထံ ဆက်သွယ်ခြင်း၊ စာလေ့လာခြင်း၊ အလုပ်လုပ်ခြင်းနှင့် Online ဖျော်ဖြေရေးအစီအစဉ်များ အသုံးပြုခြင်းစသည့် နေ့စဉ်လုပ်ငန်းများအတွက် Internet ချိတ်ဆက်မှုကို အဆင်ပြေစွာ အသုံးပြုနိုင်ပါသည်။

                ကျွန်ုပ်တို့၏ Internet ဝန်ဆောင်မှုကို ရေရှည်အသုံးပြုလာသော Customer များအတွက် ပိုမိုကောင်းမွန်သော အကျိုးခံစားခွင့်နှင့် အဆင်ပြေမှုများ ရရှိစေရန် ဤအထူးအခွင့်အရေးကို ပေးအပ်ခြင်းဖြစ်ပါသည်။

                Internet ဝန်ဆောင်မှုကို ၆ လနှင့်အထက် အသုံးပြုထားသော လူကြီးမင်းများအနေဖြင့် မိမိ၏ အရည်အချင်းပြည့်မီမှုကို စစ်ဆေးပြီး CG-NET Wi-Fi အခမဲ့ အသုံးပြုနိုင်သော အကျိုးခံစားခွင့်ကို ရယူလိုက်ပါ။

                CG-NET Wi-Fi နှင့် ပတ်သက်သော အသေးစိတ်အချက်အလက်များနှင့် အသုံးပြုနိုင်မည့် နည်းလမ်းများကို သိရှိလိုပါက QR Code ကို Scan ပြုလုပ်ခြင်း သို့မဟုတ် ကျွန်ုပ်တို့ထံ ဆက်သွယ်စုံစမ်းနိုင်ပါသည်။
                TEXT
                ,
                'description_zh' => <<<'TEXT'
                我们很高兴为使用互联网服务满 6 个月或以上的尊贵客户推出特别福利。

                符合条件的客户现在可以免费享受 CG-NET Wi-Fi，为日常互联网使用提供更加方便的连接方式。通过 CG-NET Wi-Fi，您可以在支持的地点更加便捷地访问互联网。

                无论是浏览网页、与家人朋友保持联系、学习、工作，还是享受在线娱乐，CG-NET Wi-Fi 都可以为您的日常网络使用带来更多便利。

                这项特别福利旨在为长期使用我们互联网服务的客户提供更多价值和便利。

                如果您已经使用我们的互联网服务满 6 个月或以上，欢迎查询您的资格并享受 CG-NET Wi-Fi 特别福利。

                如需了解更多关于 CG-NET Wi-Fi 及使用方式的信息，请扫描二维码或联系我们进行咨询。
                TEXT
                ,
                'image_url' => 'seeder_images/news/free_usage.png',
                'status' => NewsStatus::Published,
                'slug' => 'cg-net-free-wifi',
            ],
            [
                'category_id' => 1,
                'title_en' => 'New Branch Opening Announcement',
                'title_my' => 'ရုံးခွဲသစ် ဖွင့်လှစ်ခြင်း ကြေညာခြင်း',
                'title_zh' => '新分公司开业公告',

                'description_en' => <<<'TEXT'
                We are pleased to announce the opening of our new branch to provide more convenient and accessible services to our customers.

                The new branch has been opened to make it easier for customers in the surrounding areas to access our Internet and telecommunications services. Customers can visit the branch to learn more about our available services, Internet packages, data cards, and other service options.

                Our staff at the new branch are ready to assist customers with service inquiries, new service applications, account-related assistance, package information, and other customer service needs.

                We are continuously working to expand our service locations and improve the convenience of our customers. The opening of this new branch is another step toward providing reliable and accessible services to more customers.

                We warmly welcome you to visit our new branch and experience our services. We look forward to serving you at our new location.
                TEXT
                ,
                'description_my' => <<<'TEXT'
                လူကြီးမင်းတို့အတွက် ပိုမိုလွယ်ကူအဆင်ပြေစွာ ဝန်ဆောင်မှုပေးနိုင်ရန် ရုံးခွဲသစ်ကို ဖွင့်လှစ်လိုက်ပြီဖြစ်ကြောင်း ဝမ်းမြောက်စွာ အသိပေးအပ်ပါသည်။

                ရုံးခွဲသစ်ကို အနီးပတ်ဝန်းကျင်ရှိ လူကြီးမင်းများအနေဖြင့် ကျွန်ုပ်တို့၏ Internet နှင့် ဆက်သွယ်ရေးဝန်ဆောင်မှုများကို ပိုမိုလွယ်ကူစွာ လာရောက်အသုံးပြုနိုင်ရန် ရည်ရွယ်၍ ဖွင့်လှစ်ထားခြင်းဖြစ်ပါသည်။

                ရုံးခွဲသို့ လာရောက်ပြီး ရရှိနိုင်သော Internet ဝန်ဆောင်မှုများ၊ Internet Package များ၊ Data Card များနှင့် အခြားဝန်ဆောင်မှုများအကြောင်း အသေးစိတ် စုံစမ်းမေးမြန်းနိုင်ပါသည်။

                ထို့အပြင် ဝန်ဆောင်မှုအသစ် လျှောက်ထားခြင်း၊ Account နှင့်ပတ်သက်သော အကူအညီများ ရယူခြင်း၊ Package အချက်အလက်များ စုံစမ်းခြင်းနှင့် အခြား Customer Service လိုအပ်ချက်များအတွက်လည်း ရုံးခွဲရှိ ဝန်ထမ်းများထံတွင် အလွယ်တကူ အကူအညီရယူနိုင်ပါသည်။

                လူကြီးမင်းတို့အတွက် ပိုမိုကောင်းမွန်ပြီး အဆင်ပြေသော ဝန်ဆောင်မှုများ ပေးနိုင်ရန် ကျွန်ုပ်တို့အနေဖြင့် ဝန်ဆောင်မှုနေရာများကို ဆက်လက်တိုးချဲ့ပေးလျက်ရှိပါသည်။

                ရုံးခွဲသစ်တွင် လူကြီးမင်းတို့အား နွေးထွေးစွာ ကြိုဆိုလျက်ရှိပြီး ကျွန်ုပ်တို့၏ ဝန်ဆောင်မှုများကို လာရောက်အသုံးပြုကြရန် ဖိတ်ခေါ်အပ်ပါသည်။
                TEXT
                ,
                'description_zh' => <<<'TEXT'
                我们很高兴地宣布，新分公司现已正式开业，为客户提供更加方便、便捷的服务。

                新分公司的开设旨在方便周边地区的客户使用我们的互联网及通信服务。客户可以前往新分公司了解我们的互联网服务、套餐、数据卡以及其他相关服务。

                新分公司的工作人员将为客户提供服务咨询、新业务申请、账户相关协助、套餐信息查询以及其他客户服务支持。

                为了让更多客户能够更加方便地享受我们的服务，我们将持续扩大服务范围并不断提升客户体验。新分公司的开业也是我们进一步完善服务网络的重要一步。

                欢迎您前往我们的新分公司，我们期待在新的服务地点为您提供优质、便捷的服务。
                TEXT
                ,
                'image_url' => 'seeder_images/news/open_new_branch.png',
                'status' => NewsStatus::Published,
                'slug' => 'new-branch-opening',
            ],
            [
                'category_id' => 1,
                'title_en' => 'Get Data Card Easily with Our Application',
                'title_my' => 'Application မှာ အကြိုက်သုံး‌ဒေတာကဒ်များကို လွယ်ကူစွာ မှာယူနိုင်ပါပြီ',
                'title_zh' => '在我们的应用上轻松订购您喜欢的数据卡',

                'description_en' => <<<'TEXT'
                Getting your favorite data card is now easier and more convenient with our Mobile Application.

                You can browse the available data cards directly from the application and choose the option that best matches your Internet usage and needs. With just a few simple steps, you can select your preferred data card and place your order without having to visit a service center.

                The application allows you to conveniently check available data card options and choose the card that is suitable for your usage. This makes it easier for customers to manage their Internet needs whenever and wherever they need them.

                Whether you need additional data for daily Internet usage or want to purchase a data card for your personal needs, you can easily place your order through our application.

                Use our Mobile Application today and enjoy a simple, fast, and convenient way to order your preferred data cards.
                TEXT
                ,
                'description_my' => <<<'TEXT'
                မိမိအကြိုက်သုံး Data Card များကို ယခုအခါ ကျွန်ုပ်တို့၏ Mobile Application မှတစ်ဆင့် ပိုမိုလွယ်ကူအဆင်ပြေစွာ မှာယူနိုင်ပြီဖြစ်ပါသည်။

                Application ထဲတွင် ရရှိနိုင်သော Data Card များကို လွယ်ကူစွာ ကြည့်ရှုနိုင်ပြီး မိမိ၏ Internet အသုံးပြုမှုနှင့် လိုအပ်ချက်နှင့် ကိုက်ညီသော Data Card ကို ရွေးချယ်နိုင်ပါသည်။ အဆင့်အနည်းငယ်ဖြင့် မိမိနှစ်သက်ရာ Data Card ကို ရွေးချယ်ပြီး Service Center သို့ သွားရောက်ရန်မလိုဘဲ အလွယ်တကူ မှာယူနိုင်ပါသည်။

                Application မှတစ်ဆင့် ရရှိနိုင်သော Data Card အမျိုးအစားများကို စစ်ဆေးနိုင်ပြီး မိမိ၏ Internet အသုံးပြုမှုပုံစံနှင့် ကိုက်ညီသော Data Card ကို ရွေးချယ်နိုင်ပါသည်။ ထို့ကြောင့် လိုအပ်သည့်အချိန်တွင် မိမိ၏ Internet လိုအပ်ချက်များကို ပိုမိုလွယ်ကူစွာ စီမံနိုင်ပါသည်။

                နေ့စဉ် Internet အသုံးပြုရန်အတွက် Data ထပ်မံလိုအပ်သည်ဖြစ်စေ၊ မိမိကိုယ်ပိုင်အသုံးပြုရန် Data Card ဝယ်ယူလိုသည်ဖြစ်စေ Application မှတစ်ဆင့် အချိန်မရွေး လွယ်ကူစွာ မှာယူနိုင်ပါသည်။

                ကျွန်ုပ်တို့၏ Mobile Application ကို အသုံးပြုပြီး မိမိနှစ်သက်ရာ Data Card များကို ရိုးရှင်းမြန်ဆန်စွာ မှာယူလိုက်ပါ။
                TEXT
                ,
                'description_zh' => <<<'TEXT'
                现在，通过我们的移动应用购买您喜欢的数据卡变得更加简单方便。

                您可以直接在应用中浏览当前可用的数据卡，并根据自己的互联网使用情况和需求选择合适的数据卡。只需几个简单步骤，即可选择您喜欢的数据卡并完成订购，无需前往服务中心。

                通过移动应用，您可以方便地查看不同的数据卡选项，并选择最适合自己使用需求的数据卡，让您的互联网服务管理更加轻松。

                无论您是需要额外的数据流量来满足日常互联网使用需求，还是想为个人使用购买数据卡，都可以通过我们的应用轻松完成订购。

                立即使用我们的移动应用，以简单、快捷、方便的方式订购您喜欢的数据卡。
                TEXT
                ,
                'image_url' => 'seeder_images/news/data_card_news.png',
                'status' => NewsStatus::Published,
                'slug' => 'get-data-card',
            ],
            [
                'category_id' => 5,
                'title_en' => 'CG-NET Internship Program',
                'title_my' => 'CG-NET Internship Program',
                'title_zh' => 'CG-NET 实习生计划',

                'description_en' => <<<'TEXT'
                Are you a student or a recent graduate looking for an opportunity to gain practical experience in the technology and telecommunications industry?

                CG-NET Internship Program provides an opportunity for young and motivated individuals to learn, develop their skills, and gain valuable experience in a professional working environment.

                During the internship, interns can learn about real-world working processes, participate in team activities, and gain practical knowledge related to their area of interest. The program is designed to help students and young professionals understand how technology and telecommunications services are developed and delivered.

                Interns will have opportunities to work with experienced team members, improve their communication and teamwork skills, and develop practical skills that can support their future careers.

                We welcome students and recent graduates who are responsible, motivated, willing to learn, and interested in building their careers in technology and telecommunications.

                If you are looking for a valuable learning experience and want to take your first step toward a professional career, explore the CG-NET Internship Program and join us.

                Start learning, gain practical experience, and grow with CG-NET.
                TEXT
                ,
                'description_my' => <<<'TEXT'
                Technology နှင့် Telecommunications လုပ်ငန်းနယ်ပယ်တွင် လက်တွေ့အတွေ့အကြုံများ ရရှိလိုသော ကျောင်းသား၊ ကျောင်းသူများနှင့် ဘွဲ့ရပြီးသူများအတွက် CG-NET Internship Program ကို ပြုလုပ်ပေးထားပါသည်။

                CG-NET Internship Program သည် လူငယ်များအနေဖြင့် လုပ်ငန်းခွင်အတွင်း လက်တွေ့အတွေ့အကြုံများ ရရှိနိုင်ရန်၊ မိမိ၏ ကျွမ်းကျင်မှုများ တိုးတက်လာစေရန်နှင့် Professional Working Environment တစ်ခုအတွင်း အလုပ်လုပ်ပုံများကို လေ့လာနိုင်ရန် အခွင့်အရေးကောင်းတစ်ခု ဖြစ်ပါသည်။

                Internship ကာလအတွင်း လုပ်ငန်းခွင်၏ လက်တွေ့လုပ်ငန်းစဉ်များကို လေ့လာနိုင်ပြီး Team Activities များတွင် ပါဝင်ခြင်း၊ မိမိစိတ်ဝင်စားသော နယ်ပယ်နှင့် သက်ဆိုင်သည့် လက်တွေ့ဗဟုသုတများ ရရှိခြင်းတို့ကို ပြုလုပ်နိုင်ပါသည်။

                ထို့အပြင် အတွေ့အကြုံရှိသော Team Member များနှင့်အတူ လက်တွဲအလုပ်လုပ်ခြင်းဖြင့် Communication Skill၊ Teamwork Skill နှင့် လုပ်ငန်းခွင်တွင် လိုအပ်သော လက်တွေ့ကျွမ်းကျင်မှုများကို တိုးတက်အောင် လေ့လာနိုင်ပါသည်။

                တာဝန်ယူမှုရှိသူများ၊ ကြိုးစားလိုစိတ်ရှိသူများ၊ အသစ်အသစ်သော အရာများကို လေ့လာလိုသူများနှင့် Technology နှင့် Telecommunications နယ်ပယ်တွင် မိမိ၏ Career ကို စတင်တည်ဆောက်လိုသူများကို CG-NET Internship Program တွင် ပါဝင်ရန် ဖိတ်ခေါ်အပ်ပါသည်။

                လုပ်ငန်းခွင်အတွေ့အကြုံကောင်းများ ရရှိလိုပြီး မိမိ၏ Professional Career အတွက် ပထမဆုံးခြေလှမ်းကို စတင်လိုသူများအနေဖြင့် CG-NET Internship Program ကို လေ့လာပြီး ယခုပဲ ပါဝင်လိုက်ပါ။

                လေ့လာပါ၊ လက်တွေ့အတွေ့အကြုံရယူပါ၊ CG-NET နှင့်အတူ တိုးတက်လိုက်ပါ။
                TEXT
                ,
                'description_zh' => <<<'TEXT'
                如果您是在校学生或应届毕业生，希望在科技和电信行业获得实际工作经验，CG-NET 实习生计划将为您提供一个良好的学习和成长机会。

                CG-NET 实习生计划旨在帮助年轻人才在专业的工作环境中学习实际工作流程、提升专业技能，并积累宝贵的工作经验。

                在实习期间，实习生可以了解真实的工作流程，参与团队活动，并学习与自己感兴趣领域相关的实际知识。通过参与实际工作，您可以更好地了解科技及电信服务的开发与运营方式。

                实习生还有机会与经验丰富的团队成员一起工作，提升沟通能力、团队合作能力以及未来职业发展所需的实践技能。

                我们欢迎有责任心、积极主动、愿意学习，并希望在科技及电信行业开启职业生涯的学生和应届毕业生加入。

                如果您希望获得宝贵的学习经验，并迈出职业发展的第一步，欢迎了解 CG-NET 实习生计划并加入我们。

                学习新知识，积累实践经验，与 CG-NET 一起成长。
                TEXT
                ,
                'image_url' => 'seeder_images/news/internship_program.png',
                'status' => NewsStatus::Published,
                'slug' => 'cg-net-internship',
            ],
            [
                'category_id' => 5,
                'title_en' => 'We Are Hiring! Join Our Team',
                'title_my' => 'ဝန်ထမ်းသစ်များ ခေါ်ယူနေပါပြီ! ကျွန်ုပ်တို့၏ Team တွင် ပါဝင်လိုက်ပါ',
                'title_zh' => '我们正在招聘！加入我们的团队',

                'description_en' => <<<'TEXT'
                We are growing our team and are looking for talented, motivated, and passionate individuals to join us.

                We believe that our people are an important part of our success. We are looking for individuals who are interested in technology, enjoy learning new skills, and are ready to take on new challenges and opportunities.

                By joining our team, you will have the opportunity to work in a professional environment, develop your skills, gain valuable experience, and work together with colleagues on meaningful projects and services.

                We welcome candidates who are responsible, motivated, willing to learn, and interested in building a career in the technology and telecommunications industry.

                If you are looking for a new career opportunity and would like to grow together with our team, we invite you to explore our available positions and submit your application.

                Join our team and take the next step in your career with us.
                TEXT
                ,
                'description_my' => <<<'TEXT'
                ကျွန်ုပ်တို့၏ တိုးတက်လာနေသော Team တွင် ပါဝင်ရန် အရည်အချင်းရှိပြီး ကြိုးစားလိုစိတ်ရှိသူများကို ဝန်ထမ်းသစ်အဖြစ် ခေါ်ယူနေပါသည်။

                ကျွန်ုပ်တို့၏ အောင်မြင်တိုးတက်မှုတွင် ဝန်ထမ်းများသည် အရေးကြီးသော အခန်းကဏ္ဍမှ ပါဝင်လျက်ရှိပါသည်။ နည်းပညာကို စိတ်ဝင်စားသူများ၊ အသစ်အသစ်သော အရာများကို လေ့လာလိုသူများနှင့် စိန်ခေါ်မှုအသစ်များကို ရင်ဆိုင်ရန် အသင့်ရှိသူများကို ကျွန်ုပ်တို့၏ Team တွင် ပါဝင်ရန် ဖိတ်ခေါ်အပ်ပါသည်။

                ကျွန်ုပ်တို့၏ Team တွင် ပါဝင်ခြင်းဖြင့် Professional Working Environment တစ်ခုအတွင်း အလုပ်လုပ်နိုင်ခြင်း၊ မိမိ၏ ကျွမ်းကျင်မှုများကို တိုးတက်အောင် လေ့လာနိုင်ခြင်း၊ အတွေ့အကြုံကောင်းများ ရရှိနိုင်ခြင်းနှင့် လုပ်ဖော်ကိုင်ဖက်များနှင့်အတူ အရေးကြီးသော Project များနှင့် ဝန်ဆောင်မှုများတွင် ပူးပေါင်းပါဝင်နိုင်ခြင်းတို့ကို ရရှိနိုင်ပါသည်။

                တာဝန်ယူမှုရှိသူများ၊ ကြိုးစားလိုစိတ်ရှိသူများ၊ အသစ်အဆန်းများကို လေ့လာသင်ယူလိုသူများနှင့် Technology နှင့် Telecommunications လုပ်ငန်းနယ်ပယ်တွင် အလုပ်အကိုင်လမ်းကြောင်းတစ်ခု တည်ဆောက်လိုသူများကို ကြိုဆိုပါသည်။

                အလုပ်အကိုင်အခွင့်အလမ်းအသစ်တစ်ခုကို ရှာဖွေနေပြီး ကျွန်ုပ်တို့နှင့်အတူ တိုးတက်လိုသူများအနေဖြင့် လက်ရှိခေါ်ယူနေသော ရာထူးများကို လေ့လာကြည့်ရှုပြီး ယခုပဲ လျှောက်ထားနိုင်ပါသည်။

                ကျွန်ုပ်တို့၏ Team တွင် ပါဝင်ပြီး မိမိ၏ Career အတွက် နောက်ထပ်ခြေလှမ်းတစ်ခုကို ကျွန်ုပ်တို့နှင့်အတူ စတင်လိုက်ပါ။
                TEXT
                ,
                'description_zh' => <<<'TEXT'
                随着团队不断发展，我们正在寻找优秀、积极、有责任心并充满热情的人才加入我们的团队。

                我们相信，员工是企业持续发展和成功的重要组成部分。因此，我们欢迎对科技感兴趣、愿意学习新技能，并乐于迎接新挑战和新机会的人才加入我们。

                加入我们的团队后，您将有机会在专业的工作环境中工作，不断提升自己的技能，积累宝贵的工作经验，并与团队成员一起参与有意义的项目和服务。

                我们欢迎有责任心、积极主动、愿意学习，并希望在科技及电信行业发展职业生涯的人才。

                如果您正在寻找新的职业机会，并希望与我们一起成长，欢迎了解目前开放的职位并提交您的申请。

                加入我们的团队，与我们一起开启职业发展的下一步。
                TEXT
                ,
                'image_url' => 'seeder_images/news/hiring_news.png',
                'status' => NewsStatus::Published,
                'slug' => 'hiring',
            ],
        ])->each(fn(array $news) => News::create($news));
    }
}
