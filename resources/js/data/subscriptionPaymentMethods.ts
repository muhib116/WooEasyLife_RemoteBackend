export type SubscriptionPaymentMethod = {
    paymentPartner: string;
    account: string;
    note: string;
    steps: string[];
};

/** Client-side fallback when Inertia shared config is unavailable. */
export const subscriptionPaymentMethods: SubscriptionPaymentMethod[] = [
    {
        paymentPartner: "bKash",
        account: "01770989591",
        note: 'bKash "Send Money" ফি সাবস্ক্রিপশনের পরিমাণের সাথে যোগ হবে।',
        steps: [
            "bKash অ্যাপ খুলুন অথবা *247# ডায়াল করুন।",
            '"Send Money" বেছে নিন।',
            "নিচে দেখানো bKash নম্বরে টাকা পাঠান।",
            "প্ল্যানের মোট মূল্য (ফি সহ) লিখুন।",
            "bKash PIN দিয়ে নিশ্চিত করুন।",
            "Confirmation SMS থেকে Transaction ID কপি করে ফর্মে লিখুন।",
        ],
    },
    {
        paymentPartner: "Rocket",
        account: "01770989591",
        note: 'Rocket "Send Money" ফি সাবস্ক্রিপশনের পরিমাণের সাথে যোগ হবে।',
        steps: [
            "Rocket অ্যাপ খুলুন অথবা *322# ডায়াল করুন।",
            '"Send Money" বেছে নিন।',
            "নিচে দেখানো Rocket নম্বরে টাকা পাঠান।",
            "প্ল্যানের মোট মূল্য (ফি সহ) লিখুন।",
            "Rocket PIN দিয়ে নিশ্চিত করুন।",
            "Confirmation SMS থেকে Transaction ID কপি করে ফর্মে লিখুন।",
        ],
    },
];
