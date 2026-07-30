<?php

/**
 * FAQ long-form bodies (BN). Rules:
 * - Never use bare `/` as “vs” separator (linkify turns it into “হোম”).
 * - Prefer বনাম · ও , instead of slash compounds.
 * - Paths must exist in SeoPrerenderText / internalPathLinks labels.
 * - Mini SOP uses `list` (vertical). Related pills come from cluster_links — no path laundry lists.
 * - Aim ~350+ tokens body per question page; accordion answers 2–4 full sentences.
 */

return [
    'faq' => [
        [
            'heading' => 'এই FAQ হাব কী',
            'paragraphs' => [
                'এখানে WooEasyLife নিয়ে সেলারদের সবচেয়ে বেশি জিজ্ঞাসিত প্রশ্নগুলো টপিক অনুযায়ী সাজানো আছে। কুরিয়ার সাপোর্টের পেমেন্ট বা ওয়ারহাউস FAQ নয়—ফোকাস ফ্রড চেক, ফেক অর্ডার সুরক্ষা, COD রিটার্ন লস, কুরিয়ার অটো এন্ট্রি ও মোবাইল অ্যাপ।',
                'প্রতিটি প্রশ্নের আলাদা পেজ আছে: সরাসরি উত্তর, BD COD-এর ৳ প্রসঙ্গ, WooEasyLife-এ কীভাবে কাজ করে (শুধু শিপড ফিচার), আর ছোট SOP। টুল শুরু করতে পারেন /bd-fraud-checker বা /fake-order-protection থেকে; ট্রায়াল /pricing।',
                'নতুন সেলার হলে আগে ফ্রড চেক ও সাকসেস রেট বোঝার প্রশ্নগুলো পড়ুন। তারপর OTP, ব্ল্যাকলিস্ট ও রিটার্ন লস হিসাব—এই ক্রমে পড়লে ওয়ার্কফ্লো পরিষ্কার থাকে।',
                'SteadFast ক্লাস্টার লুপ: গাইড /steadfast-fraud-check → FAQ ইনডেক্স /blog/steadfast-fraud-check-faq → টুল /bd-fraud-checker → কেস /blog/steadfast-fraud-check-case-study → ক্যালকুলেটর /return-loss-calculator → আবার গাইড।',
            ],
        ],
        [
            'heading' => '১) ফ্রড ও কাস্টমার চেক',
            'paragraphs' => [
                'অর্ডার কনফার্মের আগে মোবাইল নম্বর দিয়ে Pathao, Steadfast ও RedX-এর হিস্টোরি আর সাকসেস রেট দেখুন। কোন রেট “ভালো”, কম হলে কী করবেন, হিস্টোরি কীভাবে পড়বেন আর ফ্রড স্কোর মানে কী—এসব প্রশ্নের উত্তর নিচের FAQ লিংকে।',
                'ফ্রি টুল: /bd-fraud-checker · /fake-customer-check · /bd-courier-ratio-checker। SteadFast ফোকাস পিলার: /steadfast-fraud-check। চেক শুধু সিগন্যাল দেয়—অন্ধ অটো-শিপ করে না; সিদ্ধান্ত আপনার।',
            ],
        ],
        [
            'heading' => '২) ফেক অর্ডার, OTP ও ব্ল্যাকলিস্ট',
            'paragraphs' => [
                'শুধু একবার চেক করলেই যথেষ্ট নয়। বারবার ফেক আটকাতে চেকআউট OTP, ডুপ্লিকেট অর্ডার ব্লক ও কাস্টমার ব্ল্যাকলিস্ট একসাথে লাগে। কখন OTP নেবেন, কীভাবে ব্লক করবেন—FAQ-তে ধাপে ধাপে আছে।',
                'সেটআপ: /fake-order-protection · /customer-verification · পূর্ণ গাইড /ki-vabe-fake-order-atkabo · ট্রায়াল /pricing।',
            ],
        ],
        [
            'heading' => '৩) COD রিটার্ন ও লস',
            'paragraphs' => [
                'রিটার্ন রেট না জানলে অ্যাড বাজেট বাড়ানো জুয়ার মতো। মাসিক লস কীভাবে হিসাব করবেন, কোন খরচ ধরবেন—রিটার্ন লস FAQ ও ক্যালকুলেটরে দেখুন।',
                'টুল: /return-loss-calculator · প্লেবুক /cod-return-reduction · ROAS /ads-roas-calculator।',
            ],
        ],
        [
            'heading' => '৪) কুরিয়ার অটো এন্ট্রি ও স্ট্যাটাস',
            'paragraphs' => [
                'ভেরিফায়েড অর্ডার Pathao, Steadfast বা RedX-এ ম্যানুয়াল কপি-পেস্ট ছাড়া বুক করলে ভুল ও সময় কমে। গাইড: /courier-auto-entry · /pathao-courier-guide · /steadfast-integration · /redx-courier-guide।',
            ],
        ],
        [
            'heading' => '৫) অ্যাপ ও মাল্টি-স্টোর',
            'paragraphs' => [
                'মোবাইলে পুশ নোটিফিকেশন, কল ID ম্যাচ ও মাল্টি-স্টোর QR পেয়ারিং দিয়ে বাইরে থেকেও অর্ডার approve বা hold করা যায়। বিস্তারিত: /woocommerce-mobile-app। মেটা AI বট বা অ্যাপ লক এখনো শিপড নয়—দাবি করি না।',
            ],
        ],
        [
            'heading' => 'এআই সারাংশ',
            'paragraphs' => [
                'WooEasyLife FAQ হাব BD COD সেলারদের জন্য ফ্রড চেক থেকে OTP, ব্ল্যাকলিস্ট, রিটার্ন লস ও কুরিয়ার অটো এন্ট্রি পর্যন্ত প্রশ্ন এক জায়গায় রাখে। নিচের প্রশ্ন তালিকা থেকে শুরু করুন, অথবা সরাসরি /bd-fraud-checker খুলুন।',
            ],
        ],
    ],

    'faq_courier_success_rate_kivabe_bujhbo' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'কুরিয়ার সাকসেস রেট মানে ওই মোবাইল নম্বরে কত অর্ডার সফল ডেলিভারি হয়েছে, আর কতগুলো ক্যানসেল, রিটার্ন বা ব্যর্থ হয়েছে—এর অনুপাত। delivered অনুপাত যত বেশি, সাধারণত ঝুঁকি তত কম; বারবার রিটার্ন দেখলে কনফার্মের আগে কল বা হোল্ড নিন।',
                'শুধু এক কুরিয়ার দেখে সিদ্ধান্ত নেবেন না। Pathao, Steadfast ও RedX একসাথে দেখলে প্যাটার্ন স্পষ্ট হয়। এক কুরিয়ারে ভালো রেকর্ড থাকলেও অন্যটিতে রিটার্ন বেশি থাকতে পারে।',
                'টুল: /bd-courier-ratio-checker · /bd-fraud-checker। টুল সিগন্যাল দেয়—অন্ধ অটো-শিপ করে না।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ',
            'paragraphs' => [
                'প্রতিটি রিটার্নে ডেলিভারি চার্জ, রিটার্ন ফি, প্যাকিং আর অ্যাড CPA যায়। দিনে ৫০ অর্ডারে ৩০% রিটার্ন হলে মাসে শত শত পার্সেল নষ্ট—রেট না পড়ে শিপ করলে এই ৳ লুকিয়ে থাকে।',
                'Facebook Ads চালালে ফেক বা রিটার্ন অর্ডার Pixel Purchase-এ গিয়ে ROAS ফুলিয়েও দেখাতে পারে। তাই সাকসেস রেট পড়া শুধু কুরিয়ার খরচ নয়—অ্যাড বাজেট রক্ষারও অংশ।',
                'মাসিক লস আগে মাপুন /return-loss-calculator দিয়ে। বিস্তারিত হিসাবের ধাপ: /faq/cod-return-loss-hisab।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে দেখবেন',
            'paragraphs' => [
                'ফ্রি চেকারে নম্বর দিলে সাপোর্টেড কুরিয়ারের হিস্টোরি ও সাকসেস রেট একসাথে আসে। অর্ডার স্ক্রিনেও ডেলিভারি হিস্টোরি দেখা যায় (প্যাকেজ অনুযায়ী)।',
                'কাস্টমার-ফোকাস ল্যান্ডিং: /fake-customer-check। পূর্ণ চেকার UI: /bd-fraud-checker। বারবার ফেক আটকাতে পরে /fake-order-protection চালু রাখুন।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'কনফার্মের আগে এই ধাপগুলো একবার করে নিন—টিমের সবাই একই রুল মানলে ভুল শিপ কমে।',
            ],
            'list' => [
                'অর্ডার থেকে কাস্টমারের বাংলাদেশি মোবাইল নম্বর নিন।',
                '/bd-fraud-checker বা /bd-courier-ratio-checker টুলে নম্বর দিয়ে চেক চালান।',
                'delivered বনাম রিটার্ন বা ক্যানসেল অনুপাত পড়ুন—শুধু একটা স্ক্রিনশট নয়, প্যাটার্ন দেখুন।',
                'সবুজ বা উচ্চ রেট হলে কনফার্ম করুন; হলুদ বা লাল জোনে /faq/success-rate-kom-hole-ki-korbo অনুসরণ করুন।',
                'নোট লিখে রাখুন এবং টিমকে একই SOP দিন।',
                'SteadFast অর্ডারে পিলার ফ্লো মিলান: /steadfast-fraud-check।',
            ],
        ],
        [
            'heading' => 'সাধারণ ভুল',
            'paragraphs' => [
                'শুধু এক কুরিয়ারের প্যানেল দেখে সিদ্ধান্ত নেওয়া; লাল জোন দেখেও “একবার ট্রাই”; রেট ভালো দেখে OTP বা নোট বন্ধ করে দেওয়া—এগুলো মাসের লস বাড়ায়।',
                'সঠিক অভ্যাস: প্রতি সন্দেহজনক অর্ডারে চেক → নোট → একই SOP। ক্লাস্টার ভুল তালিকা: /blog/steadfast-fraud-check-common-mistakes।',
            ],
        ],
        [
            'heading' => 'SteadFast ক্লাস্টার লিংক',
            'paragraphs' => [
                'SteadFast ফোকাস গাইড: /steadfast-fraud-check। Delivery Ratio ব্লগ: /blog/steadfast-delivery-ratio-ki। কখন ভেরিফাই: /faq/prottek-customer-verify-korbo-ki · /blog/kokhon-customer-verify-korbo।',
                'কত ডেলিভারি “ভালো” তা জাদু সংখ্যা নয়—ভলিউম কম হলে শতাংশ অস্থির। ৭০%+ প্রায়শই ভালো সিগন্যাল, কিন্তু মোট অর্ডার, সাম্প্রতিক রিটার্ন ও টিকেট সাইজ একসাথে দেখুন। ফোন কনফার্ম গ্যারান্টি নয়: /faq/phone-confirm-delivery-guarantee-ki।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'রেট কম হলে কী করবেন: /faq/success-rate-kom-hole-ki-korbo। হিস্টোরি কীভাবে পড়বেন: /faq/customer-delivery-history-check। স্কোর মানে কী: /faq/customer-fraud-score-ki। সব প্রশ্ন: /faq। ট্রায়াল: /pricing।',
            ],
        ],
    ],

    'faq_success_rate_kom_hole_ki_korbo' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'সাকসেস রেট কম হলে অন্ধ COD শিপ করবেন না। আগে ফোন-কনফার্ম বা চেকআউট OTP নিন, ঠিকানা পরিষ্কার করুন, প্রয়োজনে হোল্ড রাখুন বা অগ্রিম ডেলিভারি চার্জ ছাড়া শিপ করবেন না।',
                'একটা খারাপ রেকর্ড মানেই সবসময় ফেক কাস্টমার নয়। ঠিকানা সমস্যা বা এককালীন অনুপস্থিতিও হতে পারে। তাই হিস্টোরি আর ছোট কল একসাথে সবচেয়ে নিরাপদ।',
                'এখনই চেক: /bd-fraud-checker। রেট কীভাবে পড়বেন: /faq/courier-success-rate-kivabe-bujhbo।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ',
            'paragraphs' => [
                'লাল জোনে অন্ধ শিপ মানে প্রায় নিশ্চিত রিটার্ন ফি, প্যাকিং আর অ্যাড খরচ। একটা “একবার ট্রাই” অর্ডারও কয়েকশ থেকে হাজার ৳ খরচ করতে পারে—মাসে কয়েক ডজন হলে লাভ মরে যায়।',
                'টিম যদি একই নম্বর বারবার ভুল শিপ করে, লস গুণিতক হয়। নোট ও ব্ল্যাকলিস্ট ছাড়া স্টাফ একই ভুল রিপিট করে।',
                'লস মাপুন: /faq/cod-return-loss-hisab · /return-loss-calculator।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'চেকারে জোন ও রেট দেখে সিদ্ধান্ত নিন। বারবার ঝুঁকিতে /fake-order-protection দিয়ে OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চালু রাখুন।',
                'বাইরে থেকে approve বা hold করতে /woocommerce-mobile-app ব্যবহার করুন। ভেরিফায়েড অর্ডার পরে /courier-auto-entry দিয়ে বুক করুন।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'কম রেট দেখলে এই ক্রম অনুসরণ করুন—প্রতি ধাপ এড়িয়ে গেলে আবার একই ক্ষতি হয়।',
            ],
            'list' => [
                'নম্বর দিয়ে /bd-fraud-checker চালান এবং রেট নিশ্চিত করুন।',
                'কম রেট হলে আগে কল করুন—না উঠলে OTP নিন বা হোল্ড রাখুন।',
                'ঠিকানা অসম্পূর্ণ হলে কল করে পরিষ্কার করুন বা Fix with AI ব্যবহার করুন।',
                'বারবার একই নম্বর খারাপ হলে ব্ল্যাকলিস্ট করুন—বিস্তারিত /faq/woocommerce-customer-blacklist।',
                'ভেরিফায়েড হলে /courier-auto-entry দিয়ে বুকিং করুন।',
            ],
        ],
        [
            'heading' => 'জোন অনুযায়ী সিদ্ধান্ত',
            'paragraphs' => [
                'সবুজ বা উচ্চ রেট: দ্রুত কনফার্ম করে অটো এন্ট্রি। হলুদ বা মাঝারি: কল বা কন্ডিশনাল OTP, ঠিকানা যাচাই, তারপর approve। লাল বা খুব কম সাকসেস: অগ্রিম ছাড়া শিপ করবেন না—হোল্ড বা বাতিল, নোট লিখে রাখুন।',
                'OTP কখন নেবেন: /faq/cod-order-otp-kokhon। জোন গাইড: /customer-verification।',
                'SteadFast পিলার ফ্লো: /steadfast-fraud-check। কখন প্রত্যেককে ভেরিফাই: /faq/prottek-customer-verify-korbo-ki। ফেক চিনা: /faq/fake-order-chinhe-fela-jay-ki।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'রেট কীভাবে বুঝবেন: /faq/courier-success-rate-kivabe-bujhbo। OTP কখন: /faq/cod-order-otp-kokhon। পূর্ণ গাইড: /ki-vabe-fake-order-atkabo। কেস: /blog/steadfast-fraud-check-case-study। হাব: /faq।',
            ],
        ],
    ],

    'faq_cod_order_otp_kokhon' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'COD-তে সব অর্ডারে OTP লাগে না। নতুন নম্বর, কম সাকসেস রেট, সন্দেহজনক অর্ডার বা প্রথমবার কাস্টমারে চেকআউট OTP সবচেয়ে কার্যকর।',
                'পুরনো সবুজ কাস্টমারে জোর করে OTP দিলে কনভার্শন কমে যেতে পারে। ঝুঁকি জোনে চালু রাখুন, সব অর্ডারে নয়।',
                'সেটআপ: /fake-order-protection · জোন গাইড: /customer-verification।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ',
            'paragraphs' => [
                'ভুয়া নম্বর দিয়ে অর্ডার হলে আপনি প্যাকিং ও কুরিয়ার বুকিংয়ে টাকা পোড়ান। OTP ফোন ভেরিফাই করে ফেক Purchase ও পিক্সেল নয়েজও কমায়—অ্যাড ROAS আসলের কাছাকাছি থাকে।',
                'শুধু ম্যানুয়াল কল দিয়ে সব অর্ডার ধরা যায় না—ভলিউম বাড়লে OTP অটোমেটেড গেটকিপার হিসেবে কাজ করে।',
                'রিপোর্টেড বনাম আসল ROAS দেখতে: /ads-roas-calculator।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'প্লাগইনে চেকআউট OTP আছে (Store API প্যারিটিসহ), সাথে দৈনিক অর্ডার লিমিট, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট—শুধু আলাদা SMS গেটওয়ে নয়।',
                'বিস্তারিত সেটআপ: /fake-order-protection। মোবাইল থেকে follow-up: /woocommerce-mobile-app।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'OTP চালু করার আগে রুল ঠিক করুন, তারপর প্রোটেকশন লেয়ার যোগ করুন।',
            ],
            'list' => [
                'ঝুঁকি রুল ঠিক করুন—নতুন নম্বর বা কম সাকসেস রেটে OTP।',
                'OTP টেমপ্লেট ও থ্রটল সেট করুন যাতে স্প্যাম না হয়।',
                'ডুপ্লিকেট অর্ডার ব্লক চালু রাখুন—বিস্তারিত /faq/duplicate-cod-order-block।',
                'কনফার্মের আগে হিস্টোরি চেক করুন /bd-fraud-checker দিয়ে।',
                'ব্যর্থ OTP বা বারবার ফেক প্যাটার্নে ব্ল্যাকলিস্ট করুন।',
            ],
        ],
        [
            'heading' => 'OTP বনাম কল বনাম হোল্ড',
            'paragraphs' => [
                'উচ্চ রেট: সাধারণত OTP ছাড়াই কনফার্ম করা যায়। মাঝারি রেট: ছোট কল বা কন্ডিশনাল OTP। কম রেট বা নতুন নম্বর: OTP বা হোল্ড—অন্ধ শিপ নয়।',
                'কম রেট SOP: /faq/success-rate-kom-hole-ki-korbo।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'ব্ল্যাকলিস্ট: /faq/woocommerce-customer-blacklist। কম রেট SOP: /faq/success-rate-kom-hole-ki-korbo। হাব: /faq · ট্রায়াল: /pricing।',
            ],
        ],
    ],

    'faq_woocommerce_customer_blacklist' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'WooCommerce কাস্টমার ব্ল্যাকলিস্ট মানে ফোন, ইমেইল, IP বা ডিভাইস দিয়ে বারবার ফেক বা রিটার্ন কাস্টমারকে নতুন অর্ডার দিতে না দেওয়া।',
                'কুরিয়ার সাধারণত আপনার হয়ে ব্লক করে না—স্টোর-লেভেল ব্লক আপনার টুল। একবার সঠিকভাবে ব্লক করলে একই ৳ ক্ষতি বারবার হয় না।',
                'সেটআপ: /fake-order-protection। কেস স্টাডি স্টাইল পড়া: /blog/blacklist-customer-after-returns।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ',
            'paragraphs' => [
                'একই নম্বর দিয়ে বারবার রিটার্ন মানে একই ক্ষতি রিপিট। প্যাকিং, কুরিয়ার অ্যাটেম্পট ও অ্যাড CPA মাসের পর মাস জমে।',
                'শুধু নোট রাখলে স্টাফ ভুলে আবার শিপ করতে পারে। ব্ল্যাকলিস্ট চেকআউটেই আটকায়।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'ব্ল্যাকলিস্ট CRUD ও CSV ইমপোর্ট, ফোন · ইমেইল · IP · ডিভাইস ব্লক, BD-IP অপশন—প্যাকেজ অনুযায়ী। চেকআউট ও Store API প্যারিটি আছে, শুধু ম্যানুয়াল নোট নয়।',
                'OTP ও ডুপ্লিকেট ব্লকের সাথে একসাথে চালু রাখলে নতুন নম্বর দিয়ে ফিরে আসাও কমে। প্রোটেকশন: /fake-order-protection।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'ব্লক করার আগে নিশ্চিত হোন—ভুল ব্লক মানে ভালো কাস্টমার হারানো।',
            ],
            'list' => [
                'হিস্টোরি ও স্কোর দিয়ে নিশ্চিত করুন—/bd-fraud-checker।',
                'কেন ব্লক করছেন সেই নোট লিখে রাখুন।',
                'ফোন ব্লক করুন; প্রয়োজনে IP বা ডিভাইসও যোগ করুন।',
                'OTP ও ডুপ্লিকেট ব্লক চালু রাখুন যাতে নতুন নম্বর দিয়ে ফিরে না আসে।',
                'টিমকে ব্লক লিস্ট ও রুল শেয়ার করুন।',
            ],
        ],
        [
            'heading' => 'কখন ব্লক করবেন না',
            'paragraphs' => [
                'একবারের খারাপ রেকর্ড বা ঠিকানা সমস্যায় তাড়াহুড়ো ব্লক করবেন না। আগে কল ও হিস্টোরি দেখুন। বারবার লাল জোন বা স্পষ্ট ফেক প্যাটার্ন হলে ব্লক করুন।',
                'কম রেট SOP: /faq/success-rate-kom-hole-ki-korbo।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'OTP কখন: /faq/cod-order-otp-kokhon। ডুপ্লিকেট ব্লক: /faq/duplicate-cod-order-block। হাব: /faq।',
            ],
        ],
    ],

    'faq_duplicate_cod_order_block' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'ডুপ্লিকেট COD ব্লক একই কার্ট বা কাস্টমার সিগন্যালে বারবার অর্ডার আটকায়—স্প্যাম ও “টেস্ট” অর্ডারে প্যাকিং নষ্ট কমায়।',
                'সাথে দৈনিক অর্ডার লিমিট রাখলে এক নম্বরে অর্ডারের ঝড় থামে। বৈধ আলাদা অর্ডার সাধারণত পাস করতে পারে যদি রুল সঠিক হয়।',
                'সেটআপ: /fake-order-protection।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ',
            'paragraphs' => [
                'এক কাস্টমার একই জিনিস পাঁচবার অর্ডার দিলে পাঁচটা পিক-প্যাক ও কুরিয়ার অ্যাটেম্পট হতে পারে। সব রিটার্ন হলে লস গুণিতক হয়ে যায়।',
                'রাতের স্প্যাম বা বাচ্চাদের “মজা” অর্ডারও একই নম্বরে জমে—লিমিট ছাড়া স্টাফ সকালে পাহাড় দেখে।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'ডুপ্লিকেট same-cart ব্লক, চেকআউট ভ্যালিডেশন ও দৈনিক লিমিট—OTP আর ব্ল্যাকলিস্টের সাথে এক স্তর।',
                'বিস্তারিত: /fake-order-protection · গাইড: /ki-vabe-fake-order-atkabo।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'স্প্যাম কমাতে এই স্তরগুলো একসাথে চালু রাখুন।',
            ],
            'list' => [
                'ডুপ্লিকেট অর্ডার ব্লক চালু করুন।',
                'প্রতি নম্বরে দৈনিক অর্ডার লিমিট সেট করুন।',
                'ঝুঁকি জোনে OTP চালু রাখুন।',
                'কনফার্মের আগে হিস্টোরি চেক করুন।',
                'স্প্যামার নম্বর ব্ল্যাকলিস্টে রাখুন।',
            ],
        ],
        [
            'heading' => 'রুল টেস্ট করুন',
            'paragraphs' => [
                'চালু করার পর একটা টেস্ট অর্ডার দিয়ে দেখুন বৈধ অর্ডার আটকাচ্ছে কি না। খুব কঠিন রুল কনভার্শন কমাতে পারে; খুব নরম রুল স্প্যাম ছাড়ে।',
                'OTP: /faq/cod-order-otp-kokhon। ব্ল্যাকলিস্ট: /faq/woocommerce-customer-blacklist।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'OTP: /faq/cod-order-otp-kokhon। ব্ল্যাকলিস্ট: /faq/woocommerce-customer-blacklist। হাব: /faq।',
            ],
        ],
    ],

    'faq_customer_delivery_history_check' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'কাস্টমার ডেলিভারি হিস্টোরি চেক মানে মোবাইল নম্বর দিয়ে আগের কুরিয়ার ডেলিভারি ও রিটার্ন রেকর্ড দেখা। COD কনফার্ম বা পার্সেল বুকিংয়ের আগে করুন—পরে নয়।',
                'Pathao, Steadfast ও RedX একসাথে দেখলে এক নম্বরে একাধিক সিগন্যাল পাওয়া যায়। শুধু এক কুরিয়ারের প্যানেলে লগইন করে খোঁজার দরকার কমে।',
                'ফ্রি টুল: /bd-fraud-checker · /fake-customer-check · /courier-checker।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ',
            'paragraphs' => [
                'হিস্টোরি ছাড়া শিপ মানে জুয়া। একটা লাল নম্বর এড়ালেই এক রাউন্ড রিটার্ন ফি বাঁচে; মাসে দশটা এড়ালে লাখের হিসাব হতে পারে।',
                'অ্যাড স্কেল করার আগে হিস্টোরি চেক স্থিতিশীল না থাকলে ফেক Purchase ROAS ফুলিয়ে বাজেট নষ্ট করে।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'ল্যান্ডিংয়ে অ্যাকাউন্ট ছাড়াই সীমিত ফ্রি চেক করা যায়। প্লাগইন ও অ্যাপে অর্ডারে হিস্টোরি ও ফ্রড সিগন্যাল দেখা যায় (প্যাকেজ গেটেড)।',
                'সাপোর্টেড কুরিয়ার: Pathao, Steadfast, RedX। পূর্ণ UI: /bd-fraud-checker।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'চেক থেকে সিদ্ধান্ত পর্যন্ত এই ক্রম রাখুন।',
            ],
            'list' => [
                'অর্ডার থেকে মোবাইল নম্বর নিন।',
                '/bd-fraud-checker চালান।',
                'সাকসেস রেট পড়ুন—বিস্তারিত /faq/courier-success-rate-kivabe-bujhbo।',
                'সিদ্ধান্ত নিন: কনফার্ম, কল, OTP বা হোল্ড।',
                'ভেরিফায়েড হলে /courier-auto-entry দিয়ে বুকিং করুন।',
            ],
        ],
        [
            'heading' => 'হিস্টোরি পড়ার টিপস',
            'paragraphs' => [
                'শুধু সর্বশেষ একটা অর্ডার নয়—বারবার রিটার্ন বা ক্যানসেল প্যাটার্ন দেখুন। নতুন নম্বরে হিস্টোরি কম থাকতে পারে; তখন OTP বা কল নিরাপদ।',
                'কম রেট হলে: /faq/success-rate-kom-hole-ki-korbo। স্কোর আলাদা সিগন্যাল: /faq/customer-fraud-score-ki।',
                'কুরিয়ার হিস্টোরি মানেই নম্বরের পার্সেল সিভি—জাতীয় পরিচয় নয়। গভীর ব্যাখ্যা: /blog/steadfast-customer-history-ki। খালি হিস্টোরি: /faq/history-na-thakle-ki-korbo।',
            ],
        ],
        [
            'heading' => 'SteadFast ওয়ার্কফ্লো',
            'paragraphs' => [
                'SteadFast অর্ডারে আগে /steadfast-fraud-check বা /bd-fraud-checker চালান, তারপর কনফার্ম ও /courier-auto-entry। বুকিংয়ের পর cancel বা return: /steadfast-return-hub · /faq/steadfast-return-request-kivabe।',
                'ফোন কনফার্ম একা যথেষ্ট নয়: /faq/phone-confirm-delivery-guarantee-ki। টুল গ্যারান্টি নয়: /faq/wooeasylife-fraud-predict-kore-ki।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'কম রেট: /faq/success-rate-kom-hole-ki-korbo। স্কোর: /faq/customer-fraud-score-ki। পিলার: /steadfast-fraud-check। হাব: /faq।',
            ],
        ],
    ],

    'faq_customer_fraud_score_ki' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'কাস্টমার ফ্রড বা বিহেভিয়ার স্কোর একটি ঝুঁকি সিগন্যাল—আদালতের রায় বা অটো-ব্লক ভেরডিক্ট নয়। কম স্কোরে কল বা OTP নিন; শুধু স্কোর দেখে সব অর্ডার ক্যানসেল করবেন না।',
                'সাকসেস রেট বলে ডেলিভারি হিস্টোরির অনুপাত; স্কোর বলে আচরণ সিগন্যাল। দুটো আলাদা মেট্রিক—মিলিয়ে পড়ুন।',
                'দেখুন: /fake-customer-check · /bd-fraud-checker।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ',
            'paragraphs' => [
                'স্কোর আর হিস্টোরি একসাথে ফেক Purchase ও রিটার্ন কমায়—অ্যাড বাজেটের অপচয় কম। শুধু স্কোর বা শুধু অনুভূতি দুটোই ভুল পথ।',
                'টিমকে “স্কোর কম = অটো ক্যানসেল” না শিখিয়ে “স্কোর কম = কল বা OTP” শেখান—নাহলে ভালো কাস্টমারও হারাতে পারেন।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'কাস্টমার বিহেভিয়ার বা ফ্রড স্কোর মডিউল (প্যাকেজ গেটেড) অর্ডার ও কাস্টমার কনটেক্সটে সিগন্যাল দেয়।',
                'কুরিয়ার হিস্টোরি রেশিও আলাদা মেট্রিক। রেট বোঝা: /faq/courier-success-rate-kivabe-bujhbo। হিস্টোরি চেক: /faq/customer-delivery-history-check।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'স্কোরকে হিস্টোরির সাথে মিলিয়ে ব্যবহার করুন।',
            ],
            'list' => [
                'স্কোর দেখুন এবং নোট নিন।',
                'সাকসেস রেট আলাদা করে দেখুন।',
                'দুটো মিল না থাকলে কল করে নিশ্চিত করুন।',
                'বারবার খারাপ প্যাটার্ন হলে ব্ল্যাকলিস্ট করুন।',
                'ভালো সিগন্যাল হলে কনফার্ম করে অটো এন্ট্রি করুন।',
            ],
        ],
        [
            'heading' => 'স্কোর কম হলে কী করবেন',
            'paragraphs' => [
                'অন্ধ ক্যানসেল নয়। কল করুন, ঠিকানা যাচাই করুন, প্রয়োজনে OTP নিন। বারবার একই নম্বর খারাপ হলে ব্ল্যাকলিস্ট: /faq/woocommerce-customer-blacklist।',
                'কম রেট SOP: /faq/success-rate-kom-hole-ki-korbo। WooEasyLife কি ফ্রড predict করে? না: /faq/wooeasylife-fraud-predict-kore-ki। SteadFast পিলার: /steadfast-fraud-check।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'হিস্টোরি চেক: /faq/customer-delivery-history-check। সাকসেস রেট: /faq/courier-success-rate-kivabe-bujhbo। Fake customer: /fake-customer-check। হাব: /faq।',
            ],
        ],
    ],

    'faq_cod_return_loss_hisab' => [
        [
            'heading' => 'দ্রুত উত্তর',
            'paragraphs' => [
                'সহজ ফর্মুলা: মাসিক রিটার্ন লস ≈ দৈনিক অর্ডার × ৩০ × (রিটার্ন রেট ÷ ১০০) × প্রতি রিটার্নের খরচ (৳)।',
                'প্রতি রিটার্নে ধরুন কুরিয়ার চার্জ, রিটার্ন ফি, প্যাকিং আর অ্যাট্রিবিউটেড অ্যাড খরচ। শুধু কুরিয়ার ফি ধরলে লস কম দেখাবে।',
                'সংখ্যা বসিয়ে দেখুন: /return-loss-calculator।',
            ],
        ],
        [
            'heading' => 'BD COD-তে কেন গুরুত্বপূর্ণ',
            'paragraphs' => [
                'বেসলাইন ছাড়া “ফ্রড চেক চালু করেছি, কমেছে” বোঝা যায় না। দুই থেকে চার সপ্তাহ পর একই ফর্মুলায় আবার হিসাব করুন।',
                'রিপোর্টেড Facebook ROAS ফেক Purchase-এ ফুলে থাকতে পারে—রিটার্ন লস আলাদা হিসাব করুন। ROAS: /ads-roas-calculator · প্লেবুক: /cod-return-reduction।',
            ],
        ],
        [
            'heading' => 'WooEasyLife-এ কীভাবে',
            'paragraphs' => [
                'রিটার্ন লস ক্যালকুলেটরে দৈনিক অর্ডার, রিটার্ন রেট ও প্রতি রিটার্নের খরচ বসিয়ে মাসিক ৳ ও সেভিংস সিনারিও দেখুন।',
                'তারপর ফ্রড চেক ও OTP দিয়ে রেট কমিয়ে আবার হিসাব করুন। টুল: /return-loss-calculator · চেক: /bd-fraud-checker · প্রোটেকশন: /fake-order-protection।',
            ],
        ],
        [
            'heading' => 'মিনি SOP',
            'paragraphs' => [
                'হিসাব → অ্যাকশন → আবার মাপা—এই লুপ রাখুন।',
            ],
            'list' => [
                'দৈনিক অর্ডার, রিটার্ন শতাংশ ও প্রতি রিটার্নের ৳ লিখে রাখুন।',
                '/return-loss-calculator চালান এবং বেসলাইন সেভ করুন।',
                '/bd-fraud-checker ও /fake-order-protection চালু করুন।',
                'দুই থেকে চার সপ্তাহ পর আবার একই ফর্মুলায় হিসাব করুন।',
                'স্থিতিশীল হলে অ্যাড স্কেল করুন—গাইড /facebook-ads-for-woocommerce।',
            ],
        ],
        [
            'heading' => 'উদাহরণ হিসাব',
            'paragraphs' => [
                'ধরুন দৈনিক ৪০ অর্ডার, রিটার্ন ২৫%, প্রতি রিটার্নে গড়ে ৳২৫০। মাসিক রিটার্ন ≈ ৪০ × ৩০ × ০.২৫ = ৩০০ পার্সেল; লস ≈ ৩০০ × ২৫০ = ৳৭৫,০০০। রেট ১৫%-এ নামলে লস উল্লেখযোগ্যভাবে কমে।',
                'নিজের সংখ্যা বসিয়ে গ্যাপ দেখুন /return-loss-calculator-এ। চার্জ তুলনা: /courier-charge-calculator।',
            ],
        ],
        [
            'heading' => 'পরবর্তী ধাপ',
            'paragraphs' => [
                'কম রেট SOP: /faq/success-rate-kom-hole-ki-korbo। চার্জ তুলনা: /courier-charge-calculator। হাব: /faq · ট্রায়াল: /pricing।',
            ],
        ],
    ],
] + require __DIR__.'/seo_faq_spokes/step3_new_faqs.php';
