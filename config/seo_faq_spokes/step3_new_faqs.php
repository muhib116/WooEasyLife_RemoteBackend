<?php

/**
 * Step 3 — SteadFast cluster deep FAQs (new question URLs).
 * Each body targets ~600–1000 words (whitespace tokens).
 * Rules: no bare `/` as BN “vs”; paths must have PATH_LABELS.
 */

return [
    'faq_phone_confirm_delivery_guarantee_ki' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'না—ফোন কনফার্ম ডেলিভারি গ্যারান্টি দেয় না। কাস্টমার ফোনে “নিবো” বলেও রাইডার পৌঁছালে ফোন বন্ধ, ঠিকানা ভুয়া, বা রিফিউজ করতে পারেন। কনফার্ম দরকারি স্তর; কুরিয়ার হিস্টোরি ও প্রয়োজনে OTP বা অগ্রিম আরেক স্তর।',
                'SteadFast COD সেলারদের জন্য সেরা ক্রম: নম্বর চেক → ফোন কনফার্ম → ঝুঁকি জোনে OTP → তারপর বুকিং। পিলার গাইড: /steadfast-fraud-check। এখনই চেক: /bd-fraud-checker।',
                'কখন ভেরিফাই বাধ্যতামূলক: /faq/prottek-customer-verify-korbo-ki · /blog/kokhon-customer-verify-korbo।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন শুধু কল যথেষ্ট নয়',
            'paragraphs' => [
                'বাংলাদেশে COD-এ টাকা আসে ডেলিভারির পর। একটা “কনফার্মড” অর্ডার রিটার্ন হলে ডেলিভারি চার্জ, রিটার্ন চার্জ, প্যাকেজিং ও অ্যাডস খরচের অংশ একসাথে লস। ফোন কনফার্ম শুধু মুহূর্তের সম্মতি ধরে—আগের পার্সেল আচরণ ধরে না।',
                'বাস্তব প্যাটার্ন: কনফার্মের সময় নম্বর চালু, রাইডার কলে বন্ধ; বা “ভাই অর্ডার দিয়েছে জানতাম না” বলে রিফিউজ। যে নম্বরে বারবার এই প্যাটার্ন আছে, হিস্টোরিতে রিটার্ন বেশি দেখা যায়।',
                'মাসিক লস মাপুন: /return-loss-calculator · /faq/cod-return-loss-hisab। রিটার্ন কমানো: /blog/steadfast-return-komano।',
            ],
        ],
        [
            'heading' => 'কনফার্ম + হিস্টোরি একসাথে',
            'paragraphs' => [
                'হিস্টোরি কী: /faq/customer-delivery-history-check · /blog/steadfast-customer-history-ki। সাকসেস রেট: /faq/courier-success-rate-kivabe-bujhbo · /blog/steadfast-delivery-ratio-ki।',
                'উচ্চ রেট + পরিষ্কার কনফার্ম → দ্রুত পার্সেল পাঠান। কম রেট + কনফার্ম → একা বিশ্বাস নয়; OTP বা হোল্ড: /faq/success-rate-kom-hole-ki-korbo। হিস্টোরি খালি → /faq/history-na-thakle-ki-korbo।',
                'SteadFast ফোকাস টুল: /steadfast-fraud-check। মাল্টি-কুরিয়ার: /bd-fraud-checker।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'প্রতি অর্ডারে এই ক্রম রাখুন—কল এড়িয়ে শুধু চেক, বা চেক এড়িয়ে শুধু কল, দুটোই ফাঁক রাখে।',
            ],
            'list' => [
                'নম্বর দিয়ে /steadfast-fraud-check বা /bd-fraud-checker চালান।',
                'রেট ও হিস্টোরি পড়ুন।',
                'ফোন কনফার্ম করুন—ঠিকানা ও ভ্যারিয়েন্ট আবার বলুন।',
                'হলুদ বা লাল জোনে OTP নিন: /faq/cod-order-otp-kokhon।',
                'কনফার্ম হলে /courier-auto-entry দিয়ে বুকিং; পরে সমস্যায় /steadfast-return-hub।',
            ],
        ],
        [
            'heading' => 'সাধারণ ভুল',
            'paragraphs' => [
                '“কল করে নিশ্চিত—তাই চেক লাগে না।” “চেক ভালো—তাই কল লাগে না।” দুটোই ভুল চরম। টুল গ্যারান্টি নয়: /faq/wooeasylife-fraud-predict-kore-ki।',
                'আরও ভুল: /blog/steadfast-fraud-check-common-mistakes। কেস: /blog/steadfast-fraud-check-case-study।',
            ],
        ],
        [
            'heading' => 'রিয়েল উদাহরণ',
            'paragraphs' => [
                'ধরুন স্টাফ কল করে কাস্টমার বললেন নিব। SteadFast বুকিং হলো। রাইডার দুইবার কল করে না পেয়ে return request দিলেন। হিস্টোরি চেক করলে দেখা যেত এই নম্বরে আগেও তিনবার একই প্যাটার্ন। কল একা সেই প্যাটার্ন ধরে না।',
                'উল্টো উদাহরণ: হিস্টোরি ভালো, কলও পরিষ্কার—তবু OTP ছাড়া হাই-টিকেট পার্সেল পাঠিয়ে রিটার্ন এলে লস বড়। তাই টিকেট সাইজ অনুযায়ী লেয়ার বাড়ান। কেস হিসাব: /blog/steadfast-fraud-check-case-study।',
                'রিটার্ন অপস পরেও লাগে: /faq/steadfast-return-request-kivabe · /steadfast-return-hub।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'পিলার: /steadfast-fraud-check। FAQ ইনডেক্স: /blog/steadfast-fraud-check-faq। প্রোটেকশন: /fake-order-protection। সব প্রশ্ন: /faq। ট্রায়াল: /pricing।',
            ],
        ],
    ],

    'faq_history_na_thakle_ki_korbo' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'হিস্টোরি না থাকলেই অর্ডার অটো-রিজেক্ট করবেন না। নতুন সিম, প্রথমবার ক্রেতা, বা ডাটা কভারেজ ফাঁকা থাকতে পারে। স্ট্যান্ডার্ড কনফার্ম + ঝুঁকি নীতি প্রয়োগ করুন—হাই-টিকেটে OTP বা ছোট অগ্রিম বিবেচনা করুন।',
                'খালি হিস্টোরি মানে “নিরাপদ প্রমাণিত”ও নয়। সিগন্যাল নেই বলে অন্ধভাবে পার্সেল পাঠানো জুয়া। পিলার: /steadfast-fraud-check। চেক টুল: /bd-fraud-checker।',
            ],
        ],
        [
            'heading' => 'খালি হিস্টোরি কেন হয়',
            'paragraphs' => [
                'নতুন নম্বর, সম্প্রতি কেনা সিম, আগে খুব কম অনলাইন অর্ডার, বা কুরিয়ার ডাটায় মিল না পাওয়া। ব্যবসায়িক নম্বরেও কখনো রেকর্ড পাতলা থাকে।',
                'হিস্টোরি কীভাবে কাজ করে: /faq/customer-delivery-history-check · /blog/steadfast-customer-history-ki। রেশিও: /faq/courier-success-rate-kivabe-bujhbo।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কী করবেন',
            'paragraphs' => [
                'লো-টিকেট + পরিষ্কার ঠিকানা + ভালো কনফার্ম → স্ট্যান্ডার্ড ফ্লো। হাই-টিকেট বা অদ্ভুত ঠিকানা → কল গভীর করুন, OTP নিন: /faq/cod-order-otp-kokhon। জোন রুল: /customer-verification · /blog/kokhon-customer-verify-korbo · /faq/prottek-customer-verify-korbo-ki।',
                'একই দিনে একই নম্বরে একাধিক অর্ডার → ডুপ্লিকেট সন্দেহ: /faq/duplicate-cod-order-block।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'চেকারে “কোনো রেকর্ড নেই” দেখলেও স্ক্রিন ক্লোজ করে অন্ধভাবে পার্সেল পাঠাবেন না—কল ও নোট রাখুন। প্যাকেজ থাকলে অর্ডারে হিস্টোরি প্যানেল খালি থাকতে পারে; তখনও একই নীতি।',
                'ফেক অর্ডার চিনে ফেলা যায় কি না: /faq/fake-order-chinhe-fela-jay-ki। টুল কি প্রেডিক্ট করে: /faq/wooeasylife-fraud-predict-kore-ki।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'খালি হিস্টোরিতে এই ক্রম ব্যবহার করুন।',
            ],
            'list' => [
                '/steadfast-fraud-check বা /bd-fraud-checker দিয়ে নিশ্চিত করুন রেকর্ড সত্যিই খালি।',
                'ফোন কনফার্ম—নাম, ঠিকানা, ল্যান্ডমার্ক, ভ্যারিয়েন্ট।',
                'হাই-টিকেট বা সন্দেহে OTP বা অগ্রিম।',
                'কনফার্ম হলে /courier-auto-entry; নোটে “no history — verified by call” লিখুন।',
                'পরে রিটার্ন হলে প্যাটার্ন দেখে পরেরবার কঠোর করুন।',
            ],
        ],
        [
            'heading' => 'রিয়েল উদাহরণ',
            'paragraphs' => [
                'নতুন নম্বর, অর্ডার ৮০০ টাকা, ঠিকানা পরিষ্কার, কল ভালো—কনফার্ম করে অটো এন্ট্রি যুক্তিযুক্ত। নোট: no history, call OK।',
                'নতুন নম্বর, অর্ডার ৪,৫০০ টাকা, ঠিকানা অস্পষ্ট—OTP বা অগ্রিম ছাড়া পার্সেল পাঠাবেন না। ভেরিফাই নীতি: /faq/prottek-customer-verify-korbo-ki।',
                'একই দিনে তিনটা অর্ডার এক নম্বরে হিস্টোরি খালি—ডুপ্লিকেট সন্দেহ: /faq/duplicate-cod-order-block।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'কম রেট SOP (হিস্টোরি থাকলে): /faq/success-rate-kom-hole-ki-korbo। রিটার্ন কমানো: /blog/steadfast-return-komano। কেস: /blog/steadfast-fraud-check-case-study। হাব: /faq · /pricing।',
            ],
        ],
    ],

    'faq_prottek_customer_verify_korbo_ki' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'প্রত্যেক কাস্টমারে একই কঠোরতা সবসময় লাগে না। হাই ভলিউমে রিস্ক-ভিত্তিক নীতি ভালো: নতুন নম্বর, হাই টিকেট, কম সাকসেস, অদ্ভুত ঠিকানা ও অর্ডার স্পাইকে যাচাই কঠোর; পুরোনো ভালো কাস্টমারে দ্রুত পাথ।',
                'তবে “কখনো চেক করব না” নীতিও ভুল। নম্বর চেকের ৩০ সেকেন্ড অনেক রিটার্ন বাঁচায়। পিলার: /steadfast-fraud-check। ব্লগ: /blog/kokhon-customer-verify-korbo।',
            ],
        ],
        [
            'heading' => 'বাধ্যতামূলক যাচাই জোন',
            'paragraphs' => [
                'প্রথমবারের নম্বর, ২,০০০ টাকার ওপর অর্ডার, এক দিনে একই নম্বরে একাধিক অর্ডার, অসম্পূর্ণ ঠিকানা, কম সাকসেস রেট, অ্যাডস স্পাইক—এগুলোতে চেক + কনফার্ম বাধ্যতামূলক রাখুন।',
                'রেট বোঝা: /faq/courier-success-rate-kivabe-bujhbo। কম রেট: /faq/success-rate-kom-hole-ki-korbo। হিস্টোরি খালি: /faq/history-na-thakle-ki-korbo। ফোন কনফার্মের সীমা: /faq/phone-confirm-delivery-guarantee-ki।',
            ],
        ],
        [
            'heading' => 'হালকা যাচাই জোন',
            'paragraphs' => [
                'উচ্চ হিস্টোরি, পরিষ্কার ঠিকানা, লো-টিকেট, পুনরাবৃত্ত ভালো কাস্টমার—দ্রুত কনফার্ম + অটো এন্ট্রি। তবু নীতি যদি “সব নম্বর একবার চেক” হয়, স্কিপ করবেন না।',
                'টুল: /steadfast-fraud-check · /bd-fraud-checker। বুকিং: /courier-auto-entry · /steadfast-integration।',
            ],
        ],
        [
            'heading' => 'টিম নীতি',
            'paragraphs' => [
                'স্টাফকে লিখে দিন কোন জোনে কী করতে হবে—প্রতিবার মালিকের কলের অপেক্ষা বিলম্ব বাড়ায়। টার্গেট “পার্সেল পাঠানোর সংখ্যা” নয়—“ডেলিভারযোগ্য কনফার্ম”।',
                'জোন রুল পেজ: /customer-verification। প্রোটেকশন: /fake-order-protection। OTP: /faq/cod-order-otp-kokhon।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'প্রতি অর্ডারে ঝুঁকি জোন মিলিয়ে ধাপ বাছুন।',
            ],
            'list' => [
                'নম্বর চেক করুন (/steadfast-fraud-check)।',
                'জোন নির্ধারণ: সবুজ, হলুদ, লাল বা নো-হিস্টোরি।',
                'সবুজে দ্রুত কনফার্ম; হলুদ বা লালে কল বা OTP; নো-হিস্টোরিতে স্ট্যান্ডার্ড + ঝুঁকি নীতি।',
                'নোটে কারণ লিখুন।',
                'কনফার্ম হলে অটো এন্ট্রি; পরে /steadfast-return-hub।',
            ],
        ],
        [
            'heading' => 'রিয়েল উদাহরণ',
            'paragraphs' => [
                'দিনে ৮০ অর্ডার স্টোরে সব অর্ডারে গভীর কল অসম্ভব। রিস্ক জোন বাছুন—নতুন নম্বর ও হাই টিকেটে সময় খরচ করুন, বাকিতে দ্রুত পাথ।',
                'স্টাফ যদি শুধু পার্সেল পাঠানোর টার্গেট পায়, হলুদ জোনও অন্ধভাবে পার্সেল পাঠানো হবে। টার্গেট ডেলিভারযোগ্য কনফার্ম করুন। ভুল তালিকা: /blog/steadfast-fraud-check-common-mistakes।',
                'ফোন কনফার্মের সীমা মনে রাখুন: /faq/phone-confirm-delivery-guarantee-ki।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'রিটার্ন কমানো: /blog/steadfast-return-komano। ভুল: /blog/steadfast-fraud-check-common-mistakes। কেস: /blog/steadfast-fraud-check-case-study। হাব: /faq · /pricing।',
            ],
        ],
    ],

    'faq_wooeasylife_fraud_predict_kore_ki' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'না—WooEasyLife ফ্রড “predict” বা গ্যারান্টি করে না যে অর্ডার ফেক বা জেনুইন। টুল কুরিয়ার হিস্টোরি, সাকসেস রেট ও সুরক্ষা ফিচার দিয়ে better-informed decision নিতে সাহায্য করে। চূড়ান্ত সিদ্ধান্ত আপনার।',
                'সততা লাইন: This tool helps you make a better-informed decision. It does not guarantee that an order is fake or genuine. একই দর্শন: /fake-customer-check · /steadfast-fraud-check।',
            ],
        ],
        [
            'heading' => 'টুল কী করে (লাইভ)',
            'paragraphs' => [
                'মোবাইল নম্বর দিয়ে Pathao, SteadFast, RedX হিস্টোরি ও রেশিও দেখা, অর্ডারে ডেলিভারি হিস্টোরি, ঝুঁকি সিগন্যাল, আর প্রোটেকশন লেয়ার (OTP, ডুপ্লিকেট ব্লক, ব্ল্যাকলিস্ট)—প্যাকেজ অনুযায়ী।',
                'হিস্টোরি: /faq/customer-delivery-history-check। রেট: /faq/courier-success-rate-kivabe-bujhbo। স্কোর: /faq/customer-fraud-score-ki। ফেক অর্ডার চিনা: /faq/fake-order-chinhe-fela-jay-ki।',
            ],
        ],
        [
            'heading' => 'টুল কী করে না',
            'paragraphs' => [
                'জাতীয় পরিচয় যাচাই নয়, ব্যাংক বা MFS লেনদেন দেখে না, মনের ভবিষ্যৎ পড়ে না। নতুন সিম বা শেয়ার করা নম্বরে সিগন্যাল দুর্বল হতে পারে। অ্যাডস কোয়ালিটি বা স্টক মিসম্যাচ ঠিক করে না।',
                'ফোন কনফার্মও গ্যারান্টি নয়: /faq/phone-confirm-delivery-guarantee-ki।',
            ],
        ],
        [
            'heading' => 'BD সেলারদের সঠিক ব্যবহার',
            'paragraphs' => [
                'সিগন্যাল পড়ুন → নীতি অনুযায়ী কল, OTP, হোল্ড বা কনফার্ম → বুকিং → প্রয়োজনে Return Hub। স্টাফকে “টুল লাল = অটো ক্যানসেল” না শিখিয়ে “টুল লাল = যাচাই বাড়ান” শেখান।',
                'পিলার: /steadfast-fraud-check। প্রোটেকশন: /fake-order-protection। রিটার্ন অপস: /steadfast-return-hub।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'প্রেডিকশন নয়—প্রক্রিয়া।',
            ],
            'list' => [
                'চেক চালান (/bd-fraud-checker বা /steadfast-fraud-check)।',
                'রেট ও হিস্টোরি পড়ুন—ভেরডিক্ট ঘোষণা করবেন না।',
                'নীতি অনুযায়ী পরবর্তী ধাপ বাছুন।',
                'নোটে সিগন্যাল ও সিদ্ধান্ত লিখুন।',
                'পুনরাবৃত্তিতে OTP বা ব্ল্যাকলিস্ট বিবেচনা করুন।',
            ],
        ],
        [
            'heading' => 'কেন এই সততা দরকার',
            'paragraphs' => [
                'বাজারে অনেক টুল No.1 বা ১০০% ফেক ধরে বলে। সেই দাবি বিশ্বাস নষ্ট করে এবং স্টাফকে অন্ধ ক্যানসেল শেখায়। আমরা সিগন্যাল দিই—রায় দিই না।',
                'ভালো কাস্টমার এক পুরোনো রিটার্নের জন্য হারানোও লস। তাই নীতি লিখিত রাখুন। ফেক চিনার বাস্তব সীমা: /faq/fake-order-chinhe-fela-jay-ki।',
                'স্কোর ও রেট আলাদা: /faq/customer-fraud-score-ki · /faq/courier-success-rate-kivabe-bujhbo।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'কেস স্টাডি: /blog/steadfast-fraud-check-case-study। FAQ ইনডেক্স: /blog/steadfast-fraud-check-faq। সব প্রশ্ন: /faq। ট্রায়াল: /pricing।',
            ],
        ],
    ],

    'faq_fake_order_chinhe_fela_jay_ki' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'আংশিক—পুনরাবৃত্ত খারাপ হিস্টোরি, ডুপ্লিকেট অর্ডার, সন্দেহজনক প্যাটার্ন ও প্রোটেকশন সিগন্যাল দিয়ে ঝুঁকি কমানো যায়। “১০০% ফেক ধরার মেশিন” দাবি করা ভুল। অনেক রিটার্ন ফেক নয়—ঠিকানা, impulse, বা এলাকা সমস্যা।',
                'সততা লাইন: This tool helps you make a better-informed decision. It does not guarantee that an order is fake or genuine.',
                'পূর্ণ গাইড: /ki-vabe-fake-order-atkabo। পিলার: /steadfast-fraud-check। টুল গ্যারান্টি করে না: /faq/wooeasylife-fraud-predict-kore-ki।',
            ],
        ],
        [
            'heading' => 'কী সিগন্যাল দেখবেন',
            'paragraphs' => [
                'কম সাকসেস রেট + সাম্প্রতিক একাধিক রিটার্ন, একাধিক কুরিয়ারে একই খারাপ প্যাটার্ন, এক দিনে একাধিক অর্ডার, ভুয়া ঠিকানা, OTP ফেইল। হিস্টোরি: /faq/customer-delivery-history-check। রেট: /faq/courier-success-rate-kivabe-bujhbo।',
                'একটা পুরোনো রিটার্ন একা “ফেক প্রমাণ” নয়। প্যাটার্ন দেখুন।',
            ],
        ],
        [
            'heading' => 'কীভাবে আটকাবেন (লেয়ার)',
            'paragraphs' => [
                'লেয়ার ১: নম্বর চেক (/bd-fraud-checker · /steadfast-fraud-check)। লেয়ার ২: কল বা OTP (/faq/cod-order-otp-kokhon · /faq/phone-confirm-delivery-guarantee-ki)। লেয়ার ৩: ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট (/faq/duplicate-cod-order-block · /faq/woocommerce-customer-blacklist)। লেয়ার ৪: প্রোটেকশন সেটআপ (/fake-order-protection)।',
                'বুকিংয়ের পর: /steadfast-return-hub। লস মাপা: /faq/cod-return-loss-hisab · /return-loss-calculator।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'ফেক সন্দেহে তাড়াতাড়ি ক্যানসেল নয়—যাচাই বাড়ান।',
            ],
            'list' => [
                'হিস্টোরি ও রেট চেক করুন।',
                'কল করে ঠিকানা ও প্রয়োজন মিলান।',
                'সন্দেহে OTP বা হোল্ড।',
                'পুনরাবৃত্তিতে ব্ল্যাকলিস্ট + নোট।',
                'কনফার্ম হলে অটো এন্ট্রি—অন্ধভাবে পার্সেল পাঠানো নয়।',
            ],
        ],
        [
            'heading' => 'রিয়েল উদাহরণ',
            'paragraphs' => [
                'নম্বর B: ছয় পার্সেলে চার রিটার্ন, আজ হাই-টিকেট—ফেক নিশ্চিত নয়, কিন্তু অন্ধভাবে পার্সেল পাঠানো নিষেধ। কল + OTP + হোল্ড।',
                'নম্বর A: উচ্চ রেট, কিন্তু এক দিনে পাঁচ অর্ডার—ডুপ্লিকেট বা টেস্ট সন্দেহ। /faq/duplicate-cod-order-block।',
                'রিটার্ন পরে ব্ল্যাকলিস্ট: /blog/blacklist-customer-after-returns · /faq/woocommerce-customer-blacklist।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'রিটার্ন কমানো: /blog/steadfast-return-komano। কেস: /blog/steadfast-fraud-check-case-study। ভুল: /blog/steadfast-fraud-check-common-mistakes। হাব: /faq · /pricing।',
            ],
        ],
    ],

    'faq_steadfast_return_request_kivabe' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'SteadFast-এ রাইডার cancel বা return request এলে প্রতিবার পোর্টালে Change Status না ঘুরে WooEasyLife Courier → Return Requests থেকে Decide করুন—Confirm cancel বা Ask to resend। রাইডার কল ও কল লগ একই মডালে করা যায়।',
                'প্রোডাক্ট ল্যান্ডিং: /steadfast-return-hub। API সেটআপ: /steadfast-integration। এটা বুকিংয়ের পরের লেয়ার—পার্সেল পাঠানোর আগের চেকের বদলি নয়: /steadfast-fraud-check।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ',
            'paragraphs' => [
                'পোর্টালে কিউ ফেলে রাখলে স্টাফ সময় নষ্ট হয় এবং কিছু অর্ডার কলে বাঁচানো যেত। তাড়াহুড়ো ক্যানসেলও লস। Return Hub অপসকে এক জায়গায় আনে।',
                'পার্সেল পাঠানোর আগের ফিল্টার থাকলেই Pending কম আসে: /blog/steadfast-return-komano · /bd-fraud-checker।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে (লাইভ)',
            'paragraphs' => [
                'package `courier_automation` + SteadFast পোর্টাল লগইন। Pending থেকে Decide → Confirm cancel বা Ask to resend। AI সাজেশন থাকতে পারে (`ai_intelligence`)—বাধ্যতামূলক নয়। Pathao বা RedX-এ একই Return Hub দাবি করি না।',
                'Stuck পার্সেল আলাদা প্রশ্ন: /faq/steadfast-stuck-parcel-ki-korbo।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'প্রতি Pending আইটেমে এই ক্রম।',
            ],
            'list' => [
                'Courier → Return Requests খুলুন; Refresh দিন।',
                'নোট ও অর্ডার দেখুন; প্রয়োজনে রাইডার কল করুন।',
                'Confirm cancel বা Ask to resend Decide করুন।',
                'অর্ডার বাঁচানোর সুযোগ থাকলে কাস্টমার কল বা WhatsApp।',
                'আগে চেক নীতি শক্ত করুন যাতে Pending কমে: /steadfast-fraud-check।',
            ],
        ],
        [
            'heading' => 'রিয়েল উদাহরণ',
            'paragraphs' => [
                'রাইডার নোট: কাস্টমার ফোন ধরে না। Decide-এ রাইডার কল করে দেখা গেল ভুল সময়ে গিয়েছিলেন—Ask to resend সেভ করতে পারে।',
                'নোট: কাস্টমার রিফিউজ, দাম বেশি মনে হচ্ছে—Confirm cancel যুক্তিযুক্ত; একই নম্বরে পরের অর্ডারে চেক কঠোর করুন: /steadfast-fraud-check।',
                'AI সাজেশন থাকলেও চূড়ান্ত ক্লিক আপনার—বাধ্যতামূলক নয়।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'হাব: /steadfast-return-hub। অটো এন্ট্রি: /courier-auto-entry। Stuck: /faq/steadfast-stuck-parcel-ki-korbo। সব FAQ: /faq। ট্রায়াল: /pricing।',
            ],
        ],
    ],

    'faq_steadfast_stuck_parcel_ki_korbo' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'SteadFast পার্সেল কয়েকদিন নিস্তব্ধ থাকলে WooEasyLife Notifications-এ Scan stuck ব্যবহার করুন (সাধারণত ~৩ দিন quiet)। Stuck মানেই সবসময় cancel নয়—রাইডার ব্যস্ত বা নেটওয়ার্ক সমস্যাও হতে পারে। আগে ধরলে কল বা return ফ্লো তাড়াতাড়ি শুরু হয়।',
                'ল্যান্ডিং: /steadfast-return-hub। সেটআপ: /steadfast-integration। পার্সেল পাঠানোর আগে ঝুঁকি কমাতে: /steadfast-fraud-check।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ',
            'paragraphs' => [
                'দেরি হলে কাস্টমার রাগ করে রিফিউজ করতে পারেন—অতিরিক্ত রিটার্ন লস। পোর্টালে ম্যানুয়াল খোঁজা সময় সাপেক্ষ। Stuck স্ক্যান সাপ্তাহিক অভ্যাস রাখুন।',
                'লস মাপা: /return-loss-calculator · /faq/cod-return-loss-hisab।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে (লাইভ)',
            'paragraphs' => [
                'Courier → Notifications: portal আপডেট, রাইডার নোট, cancel সিগন্যাল। Scan stuck দিয়ে নিস্তব্ধ পার্সেল ধরুন। Return request থাকলে Decide: /faq/steadfast-return-request-kivabe।',
                'শুধু SteadFast—Pathao বা RedX stuck হাব এখানে দাবি নয়।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'সাপ্তাহিক stuck রুটিন।',
            ],
            'list' => [
                'Notifications খুলে Scan stuck চালান।',
                'প্রতিটি আইটেমে স্ট্যাটাস ও নোট দেখুন।',
                'প্রয়োজনে রাইডার বা কাস্টমার কল।',
                'Cancel বা return দরকার হলে Return Requests → Decide।',
                'পুনরাবৃত্ত এলাকা সমস্যায় ডেলিভারি প্রমিস ও জোন রুল মিলান: /customer-verification।',
            ],
        ],
        [
            'heading' => 'রিয়েল উদাহরণ',
            'paragraphs' => [
                'তিন দিন আপডেট নেই—স্ক্যানে এলো। কল করে দেখা গেল রাইডার এলাকায় নেটওয়ার্ক সমস্যা। কাস্টমারকে WhatsApp আপডেট দিলে রিফিউজ কমে।',
                'Stuck + cancel request একসাথে থাকলে আগে নোট পড়ে Decide: /faq/steadfast-return-request-kivabe।',
                'পুনরাবৃত্ত এলাকায় ডেলিভারি প্রমিস মিলান: /customer-verification।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'Return request: /faq/steadfast-return-request-kivabe। পিলার: /steadfast-fraud-check। রিটার্ন কমানো: /blog/steadfast-return-komano। হাব: /faq · /pricing।',
            ],
        ],
    ],
];
