<?php

/**
 * Long-form FAQ body sections (BN). Format per SEO skill:
 * direct answer → BD COD math → WooEasyLife how → mini SOP → related/CTA.
 */

return [
    'faq' => [
        [
            'heading' => 'এই FAQ হাব কী',
            'paragraphs' => [
                'এখানে WooEasyLife-এর সবচেয়ে জিজ্ঞাসিত প্রশ্নগুলো টপিক অনুযায়ী সাজানো—কুরিয়ার সাপোর্টের পেমেন্ট/ওয়ারহাউস FAQ নয়। ফোকাস: ফ্রড চেক, ফেক অর্ডার সুরক্ষা, COD রিটার্ন লস, কুরিয়ার অটো এন্ট্রি ও মোবাইল অ্যাপ।',
                'প্রতিটি প্রশ্নের নিজস্ব URL আছে—সরাসরি উত্তর, BD COD ৳ প্রসঙ্গ, WooEasyLife-এ কীভাবে (শুধু শিপড ফিচার), এবং ছোট SOP। টুল: /bd-fraud-checker, /fake-order-protection, /return-loss-calculator, /pricing।',
            ],
        ],
        [
            'heading' => '১) ফ্রড / কাস্টমার চেক',
            'paragraphs' => [
                'অর্ডার কনফার্মের আগে মোবাইল নম্বর দিয়ে Pathao, Steadfast, RedX হিস্টোরি ও সাকসেস রেট দেখুন। প্রশ্ন: /faq/courier-success-rate-kivabe-bujhbo, /faq/success-rate-kom-hole-ki-korbo, /faq/customer-delivery-history-check, /faq/customer-fraud-score-ki।',
                'ফ্রি টুল: /bd-fraud-checker · /fake-customer-check · /bd-courier-ratio-checker।',
            ],
        ],
        [
            'heading' => '২) ফেক অর্ডার / OTP / ব্ল্যাকলিস্ট',
            'paragraphs' => [
                'শুধু চেক যথেষ্ট নয়—বারবার ফেক আটকাতে চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট লাগে। প্রশ্ন: /faq/cod-order-otp-kokhon, /faq/woocommerce-customer-blacklist, /faq/duplicate-cod-order-block।',
                'সেটআপ ও ট্রায়াল: /fake-order-protection · /customer-verification · /pricing। ধাপে ধাপে: /ki-vabe-fake-order-atkabo।',
            ],
        ],
        [
            'heading' => '৩) COD রিটার্ন ও লস',
            'paragraphs' => [
                'রিটার্ন রেট না জানলে অ্যাড বাজেট বাড়ানো জুয়া। হিসাব: /faq/cod-return-loss-hisab · ক্যালকুলেটর: /return-loss-calculator · প্লেবুক: /cod-return-reduction · ROAS: /ads-roas-calculator।',
            ],
        ],
        [
            'heading' => '৪) কুরিয়ার অটো এন্ট্রি ও স্ট্যাটাস',
            'paragraphs' => [
                'ভেরিফায়েড অর্ডার Pathao/Steadfast/RedX-এ ম্যানুয়াল কপি-পেস্ট ছাড়া বুক করুন—ভুল ও সময় কমে। গাইড: /courier-auto-entry · /pathao-courier-guide · /steadfast-integration · /redx-courier-guide।',
            ],
        ],
        [
            'heading' => '৫) অ্যাপ / মাল্টি-স্টোর (সংক্ষিপ্ত)',
            'paragraphs' => [
                'মোবাইলে পুশ, কল ID ম্যাচ, মাল্টি-স্টোর QR পেয়ারিং দিয়ে বাইরে থেকেও approve/hold। বিস্তারিত: /woocommerce-mobile-app। মেটা AI বট বা অ্যাপ লক এখনো শিপড নয়—দাবি করি না।',
            ],
        ],
        [
            'heading' => 'এআই সারাংশ',
            'paragraphs' => [
                'WooEasyLife FAQ হাব BD COD সেলারদের ফ্রড চেক → OTP/ব্লক → রিটার্ন লস → কুরিয়ার অটো এন্ট্রি প্রশ্ন এক জায়গায় রাখে। নিচের প্রশ্ন লিংক থেকে শুরু করুন, বা সরাসরি /bd-fraud-checker খুলুন।',
            ],
        ],
    ],

    'faq_courier_success_rate_kivabe_bujhbo' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'কুরিয়ার সাকসেস রেট মানে ওই মোবাইল নম্বরে কত অর্ডার সফল ডেলিভার্ড হয়েছে বনাম ক্যানসেল/রিটার্ন/ব্যর্থ। উচ্চ delivered অনুপাত = কম ঝুঁকি; বারবার রিটার্ন = কনফার্মের আগে কল বা হোল্ড।',
                'এক কুরিয়ার নয়—Pathao, Steadfast, RedX একসাথে দেখলে প্যাটার্ন স্পষ্ট। টুল: /bd-courier-ratio-checker · /bd-fraud-checker।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ (৳)',
            'paragraphs' => [
                'প্রতি রিটার্নে ডেলিভারি+রিটার্ন চার্জ, প্যাকিং ও অ্যাড CPA যায়। দিনে ৫০ অর্ডারে ৩০% রিটার্ন হলে মাসে শত শত পার্সেল নষ্ট—রেট না পড়ে শিপ করলে এই ৳ লুকিয়ে থাকে।',
                'মাসিক লস দেখতে: /return-loss-calculator · বিস্তারিত হিসাব: /faq/cod-return-loss-hisab।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে দেখবেন',
            'paragraphs' => [
                'ফ্রি চেকারে নম্বর দিলে সাপোর্টেড কুরিয়ারের হিস্টোরি ও সাকসেস রেট একসাথে আসে। অর্ডার স্ক্রিনেও ডেলিভারি হিস্টোরি দেখা যায় (প্যাকেজ অনুযায়ী)। অন্ধ অটো-শিপ করে না—সিদ্ধান্ত আপনার।',
                'কাস্টমার অ্যাঙ্গেল: /fake-customer-check। পূর্ণ UI: /bd-fraud-checker।',
            ],
        ],
        [
            'heading' => 'মিনি SOP (৩–৫ ধাপ)',
            'paragraphs' => [
                '১) অর্ডার কনফার্মের আগে নম্বর কপি করুন। ২) /bd-fraud-checker বা /bd-courier-ratio-checker চালান। ৩) delivered vs return পড়ুন। ৪) সবুজ হলে কনফার্ম; হলুদ/লালে /faq/success-rate-kom-hole-ki-korbo অনুসরণ। ৫) নোট রেখে টিমকে একই SOP দিন।',
            ],
        ],
        [
            'heading' => 'সম্পর্কিত ও পরবর্তী ধাপ',
            'paragraphs' => [
                'রেট কম: /faq/success-rate-kom-hole-ki-korbo · হিস্টোরি: /faq/customer-delivery-history-check · স্কোর: /faq/customer-fraud-score-ki · হাব: /faq · ট্রায়াল: /pricing।',
            ],
        ],
    ],

    'faq_success_rate_kom_hole_ki_korbo' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'সাকসেস রেট কম হলে অন্ধ COD শিপ করবেন না। আগে ফোন-কনফার্ম বা চেকআউট OTP নিন; ঠিকানা পরিষ্কার করুন; প্রয়োজনে হোল্ড বা অগ্রিম ডেলিভারি চার্জ ছাড়া শিপ করবেন না।',
                'একটা খারাপ রেকর্ড = সবসময় ফেক নয়—হিস্টোরি + ছোট কল একসাথে নিরাপদ। চেক: /bd-fraud-checker।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ (৳)',
            'paragraphs' => [
                'লাল জোন অন্ধ শিপ মানে প্রায় নিশ্চিত রিটার্ন ফি + প্যাকিং + অ্যাড খরচ। একটা “ট্রাই” অর্ডারও ৳৫০০–১৫০০+ খরচ করতে পারে—মাসে কয়েক ডজন হলে লাভ মরে।',
                'লস মাপুন: /faq/cod-return-loss-hisab · /return-loss-calculator।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'চেকারে জোন/রেট দেখে সিদ্ধান্ত নিন। বারবার ঝুঁকিতে /fake-order-protection-এ OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চালু রাখুন। মোবাইল থেকে approve/hold: /woocommerce-mobile-app।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                '১) নম্বর চেক (/bd-fraud-checker)। ২) কম রেট হলে কল—না উঠলে OTP বা হোল্ড। ৩) ঠিকানা অসম্পূর্ণ হলে Fix/কল। ৪) বারবার একই নম্বর খারাপ হলে ব্ল্যাকলিস্ট (/faq/woocommerce-customer-blacklist)। ৫) ভেরিফায়েড হলে /courier-auto-entry।',
            ],
        ],
        [
            'heading' => 'সম্পর্কিত',
            'paragraphs' => [
                'রেট বোঝা: /faq/courier-success-rate-kivabe-bujhbo · OTP: /faq/cod-order-otp-kokhon · গাইড: /ki-vabe-fake-order-atkabo · /faq।',
            ],
        ],
    ],

    'faq_cod_order_otp_kokhon' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'COD-তে OTP সবসময় নয়—নতুন নম্বর, কম সাকসেস রেট, সন্দেহজনক অর্ডার বা প্রথমবার কাস্টমারে চেকআউট OTP সবচেয়ে কার্যকর। পুরনো সবুজ কাস্টমারে অতিরিক্ত ফ্রিকশন কনভার্শন কমাতে পারে।',
                'সেটআপ: /fake-order-protection · জোন গাইড: /customer-verification।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন (৳)',
            'paragraphs' => [
                'ভুয়া নম্বর দিয়ে অর্ডার হলে আপনি প্যাক ও কুরিয়ার বুকিংয়ে টাকা পোড়ান। OTP ফোন ভেরিফাই করে ফেক পারচেজ/পিক্সেল নয়েজও কমায়—অ্যাড ROAS আসলের কাছাকাছি থাকে।',
                'ROAS দেখুন: /ads-roas-calculator।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'প্লাগইনে চেকআউট OTP (Store API প্যারিটিসহ), দৈনিক অর্ডার লিমিট, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট একসাথে পাওয়া যায়—শুধু SMS গেটওয়ে নয়। বিস্তারিত: /fake-order-protection।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                '১) ঝুঁকি রুল ঠিক করুন (নতুন/কম রেট)। ২) OTP টেমপ্লেট ও থ্রটল সেট করুন। ৩) ডুপ্লিকেট ব্লক চালু (/faq/duplicate-cod-order-block)। ৪) কনফার্মের আগে হিস্টোরি চেক। ৫) ব্যর্থ OTP/বারবার ফেক → ব্ল্যাকলিস্ট।',
            ],
        ],
        [
            'heading' => 'সম্পর্কিত',
            'paragraphs' => [
                'ব্ল্যাকলিস্ট: /faq/woocommerce-customer-blacklist · কম রেট: /faq/success-rate-kom-hole-ki-korbo · /faq · /pricing।',
            ],
        ],
    ],

    'faq_woocommerce_customer_blacklist' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'WooCommerce কাস্টমার ব্ল্যাকলিস্ট মানে ফোন, ইমেইল, IP বা ডিভাইস দিয়ে বারবার ফেক/রিটার্ন কাস্টমারকে নতুন অর্ডার দিতে না দেওয়া। কুরিয়ার সাধারণত আপনার হয়ে ব্লক করে না—স্টোর-লেভেল ব্লক আপনার টুল।',
                'সেটআপ: /fake-order-protection · কেস: /blog/blacklist-customer-after-returns।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন (৳)',
            'paragraphs' => [
                'একই নম্বর দিয়ে বারবার রিটার্ন মানে একই ৳ ক্ষতি রিপিট। একবার ব্লক = মাসের বাকি অর্ডারে সেই লস বন্ধ।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'ব্ল্যাকলিস্ট CRUD + CSV, ফোন/ইমেইল/IP/ডিভাইস ব্লক, BD-IP অপশন—প্যাকেজ অনুযায়ী। চেকআউট ও Store API প্যারিটি। শুধু ম্যানুয়াল নোট নয়।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                '১) হিস্টোরি/স্কোর দিয়ে নিশ্চিত করুন (/bd-fraud-checker)। ২) নোট লিখুন। ৩) ফোন (+ প্রয়োজনে IP/ডিভাইস) ব্লক। ৪) OTP ও ডুপ্লিকেট ব্লক চালু রাখুন। ৫) টিমকে ব্লক লিস্ট শেয়ার করুন।',
            ],
        ],
        [
            'heading' => 'সম্পর্কিত',
            'paragraphs' => [
                'OTP: /faq/cod-order-otp-kokhon · ডুপ্লিকেট: /faq/duplicate-cod-order-block · /faq।',
            ],
        ],
    ],

    'faq_duplicate_cod_order_block' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'ডুপ্লিকেট COD ব্লক একই কার্ট/কাস্টমার সিগন্যালে বারবার অর্ডার আটকায়—স্প্যাম ও “টেস্ট” অর্ডারে প্যাকিং নষ্ট কমায়। সাথে দৈনিক অর্ডার লিমিট রাখলে এক নম্বরে বন্যা থামে।',
                'সেটআপ: /fake-order-protection।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন (৳)',
            'paragraphs' => [
                'এক কাস্টমার ৫টা একই অর্ডার দিলে ৫টা পিক-প্যাক ও কুরিয়ার অ্যাটেম্পট হতে পারে—সব রিটার্ন হলে লস গুণিতক।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'ডুপ্লিকেট same-cart ব্লক, চেকআউট ভ্যালিডেশন, দৈনিক লিমিট—OTP ও ব্ল্যাকলিস্টের সাথে এক স্তর। বিস্তারিত: /fake-order-protection · গাইড: /ki-vabe-fake-order-atkabo।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                '১) ডুপ্লিকেট ব্লক চালু। ২) দৈনিক লিমিট সেট। ৩) OTP ঝুঁকি জোনে। ৪) কনফার্মের আগে হিস্টোরি চেক। ৫) স্প্যামার ব্ল্যাকলিস্ট।',
            ],
        ],
        [
            'heading' => 'সম্পর্কিত',
            'paragraphs' => [
                'OTP: /faq/cod-order-otp-kokhon · ব্ল্যাকলিস্ট: /faq/woocommerce-customer-blacklist · /faq।',
            ],
        ],
    ],

    'faq_customer_delivery_history_check' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'কাস্টমার ডেলিভারি হিস্টোরি চেক = মোবাইল নম্বর দিয়ে আগের কুরিয়ার ডেলিভারি/রিটার্ন রেকর্ড দেখা। COD কনফার্ম বা পার্সেল বুকিংয়ের আগে করুন।',
                'ফ্রি টুল: /bd-fraud-checker · /fake-customer-check · /courier-checker।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন (৳)',
            'paragraphs' => [
                'হিস্টোরি ছাড়া শিপ = জুয়া। একটা লাল নম্বর এড়ালেই এক রাউন্ড রিটার্ন ফি বাঁচে; মাসে দশটা এড়ালে লাখের হিসাব হতে পারে।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'ল্যান্ডিংয়ে অ্যাকাউন্ট ছাড়াই সীমিত ফ্রি চেক। প্লাগইন/অ্যাপে অর্ডারে হিস্টোরি ও ফ্রড সিগন্যাল (প্যাকেজ গেটেড)। কুরিয়ার: Pathao, Steadfast, RedX।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                '১) নম্বর নিন। ২) /bd-fraud-checker চালান। ৩) রেট পড়ুন (/faq/courier-success-rate-kivabe-bujhbo)। ৪) সিদ্ধান্ত: কনফার্ম / কল / হোল্ড। ৫) ভেরিফায়েড হলে /courier-auto-entry।',
            ],
        ],
        [
            'heading' => 'সম্পর্কিত',
            'paragraphs' => [
                'কম রেট: /faq/success-rate-kom-hole-ki-korbo · স্কোর: /faq/customer-fraud-score-ki · /faq।',
            ],
        ],
    ],

    'faq_customer_fraud_score_ki' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'কাস্টমার ফ্রড/বিহেভিয়ার স্কোর একটি ঝুঁকি সিগন্যাল—আদালতের রায় বা অটো-ব্লক ভেরডিক্ট নয়। কম স্কোরে কল/OTP নিন; শুধু স্কোর দেখে সব ক্যানসেল করবেন না।',
                'দেখুন: /fake-customer-check · /bd-fraud-checker।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন (৳)',
            'paragraphs' => [
                'স্কোর + হিস্টোরি একসাথে ফেক Purchase ও রিটার্ন কমায়—অ্যাড বাজেটের অপচয় কম। শুধু স্কোর বা শুধু ফিলিং দুটোই ভুল।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'কাস্টমার বিহেভিয়ার/ফ্রড স্কোর মডিউল (প্যাকেজ গেটেড) অর্ডার/কাস্টমার কনটেক্সটে সিগন্যাল দেয়। কুরিয়ার হিস্টোরি রেশিও আলাদা মেট্রিক—দুটো মিলিয়ে পড়ুন।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                '১) স্কোর দেখুন। ২) সাকসেস রেট দেখুন। ৩) মিল না থাকলে কল। ৪) বারবার খারাপ হলে ব্ল্যাকলিস্ট। ৫) ভালো হলে কনফার্ম + অটো এন্ট্রি।',
            ],
        ],
        [
            'heading' => 'সম্পর্কিত',
            'paragraphs' => [
                'হিস্টোরি: /faq/customer-delivery-history-check · রেট: /faq/courier-success-rate-kivabe-bujhbo · /faq।',
            ],
        ],
    ],

    'faq_cod_return_loss_hisab' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'সহজ ফর্মুলা: মাসিক রিটার্ন লস ≈ দৈনিক অর্ডার × ৩০ × (রিটার্ন রেট ÷ ১০০) × প্রতি রিটার্নের খরচ (৳)। প্রতি রিটার্নে ধরুন কুরিয়ার চার্জ + রিটার্ন ফি + প্যাকিং + অ্যাট্রিবিউটেড অ্যাড খরচ।',
                'ক্যালকুলেটর: /return-loss-calculator।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন (৳)',
            'paragraphs' => [
                'বেসলাইন ছাড়া “ফ্রড চেক চালু করেছি, কমেছে” বোঝা যায় না। রিপোর্টেড Facebook ROAS ফেক Purchase-এ ফুলে থাকতে পারে—রিটার্ন লস আলাদা হিসাব করুন।',
                'ROAS: /ads-roas-calculator · কমানোর প্লেবুক: /cod-return-reduction।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'রিটার্ন লস ক্যালকুলেটরে সংখ্যা বসিয়ে মাসিক ৳ ও সেভিংস সিনারিও দেখুন। তারপর ফ্রড চেক + OTP দিয়ে রেট কমান—আবার হিসাব করুন।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                '১) দৈনিক অর্ডার, রিটার্ন %, প্রতি রিটার্ন ৳ লিখুন। ২) /return-loss-calculator চালান। ৩) /bd-fraud-checker ও /fake-order-protection চালু। ৪) ২–৪ সপ্তাহ পর আবার হিসাব। ৫) স্থিতিশীল হলে অ্যাড স্কেল (/facebook-ads-for-woocommerce)।',
            ],
        ],
        [
            'heading' => 'সম্পর্কিত',
            'paragraphs' => [
                'কম রেট SOP: /faq/success-rate-kom-hole-ki-korbo · চার্জ তুলনা: /courier-charge-calculator · /faq · /pricing।',
            ],
        ],
    ],
];
