<?php

namespace App\Services\Arcade\Games;

use App\Services\Arcade\QuestionGame;

/**
 * Cybersecurity Mini Challenge — "phishing or safe?" scenarios (24) with red-flag explanations.
 * Brand names are fictional (HimalPay, Sagar Bank...) so we never imitate a real company.
 */
class CyberGame extends QuestionGame
{
    protected int $secondsPerQuestion = 30;

    public function key(): string
    {
        return 'cyber';
    }

    protected function shuffleOptions(): bool
    {
        return false;
    }

    protected function clientMeta(array $item): array
    {
        return ['channel' => $item['channel'], 'from' => $item['from'], 'subject' => $item['subject'] ?? null, 'body' => $item['body'], 'link' => $item['link'] ?? null];
    }

    protected function bank(): array
    {
        $opts = ['Phishing / scam', 'Looks safe'];
        $s = fn ($channel, $from, $subject, $body, $link, bool $phish, $explain, array $flags = []) => [
            'channel' => $channel, 'from' => $from, 'subject' => $subject, 'body' => $body, 'link' => $link,
            'q' => 'Is this '.strtolower($channel).' phishing or safe?', 'options' => $opts,
            'answer' => $phish ? 0 : 1, 'explain' => $explain, 'flags' => $flags,
        ];

        return [
            $s('SMS', '+977 98XXXXXX21', null, 'Congratulations! Your number won Rs 25,00,000 in the HimalPay lucky draw. Pay Rs 5,000 processing fee to claim today.', null, true, 'Real prizes never ask you to pay a fee first. Unknown personal number, huge prize, urgency.', ['Pay-to-claim prize', 'Personal mobile sender', 'Urgency: "today"']),
            $s('SMS', 'SAGARBANK', null, 'Your OTP for login is 482913. Valid for 5 minutes. Never share this code with anyone, including bank staff.', null, false, 'You requested it, it comes from the bank\'s registered sender ID, and it tells you not to share it. Safe, as long as you never read the code out to anyone.', []),
            $s('Email', 'support@himalpay-verify.co', 'Account suspended', 'Dear customer, your wallet is suspended due to KYC failure. Verify within 2 hours or lose your balance.', 'http://himalpay-verify.co/kyc', true, 'Look-alike domain, threat and short deadline. Real wallets ask you to update KYC inside the official app.', ['Look-alike domain', 'Threat to lose money', 'Non-HTTPS link']),
            $s('Call', 'Caller claiming "bank head office"', null, 'We are blocking a suspicious transaction. Please tell us the 6-digit code we just sent so we can stop it.', null, true, 'No bank ever asks for an OTP over the phone. The "code" actually authorises the scammer\'s transaction.', ['Asks for OTP', 'Unsolicited call', 'Fear of loss']),
            $s('Website', 'Browser address bar', null, 'Licence renewal appointment page with your queue details, opened from the link printed on your token slip.', 'https://www.dotm.gov.np/', false, 'Official .gov.np domain over HTTPS, reached from a source you trust (your printed slip).', []),
            $s('Social', 'Facebook page "Nepal Visa Lottery 2026"', null, 'Get a guaranteed work visa to Europe! Send your passport photo and Rs 15,000 via wallet to reserve your slot.', null, true, 'Guaranteed visas and up-front payments to wallets are classic job/visa scams. Never send passport copies to unverified pages.', ['Guaranteed outcome', 'Payment to personal wallet', 'Asks for ID documents']),
            $s('Email', 'hr@yourcompany.com.np', 'Team lunch on Friday', 'Hi all, we are doing team lunch at 1 pm Friday at the usual place. Reply if you have dietary needs.', null, false, 'Expected sender from your own organisation, no links, no requests for money or credentials.', []),
            $s('SMS', 'NEA-BILL', null, 'Dear consumer, your electricity will be cut tonight. Pay pending bill now: bit.ly/nea-pay-now', 'bit.ly/nea-pay-now', true, 'Shortened link plus a scare tactic. Pay utility bills only via the official app or counter.', ['Shortened URL', 'Disconnection threat', 'Urgency: "tonight"']),
            $s('App', 'HimalPay app notification', null, 'You received Rs 1,200 from Sita K. Balance updated.', null, false, 'An in-app notification inside the official app that asks you to do nothing is normal.', []),
            $s('Email', 'it-helpdesk@gmaiI.com', 'Mailbox full', 'Your mailbox is 99% full. Login here to increase storage or emails will be deleted.', 'http://mail-upgrade.web.app/login', true, '"gmaiI" uses a capital I instead of l, the link is not your provider\'s domain, and it asks for your password.', ['Misspelled sender', 'Credential request', 'Threat of deletion']),
            $s('SMS', 'Unknown number', null, 'Hi Mum, I broke my phone. This is my new number. Can you send Rs 8,000 for a new SIM urgently? Don\'t call, I can\'t talk.', null, true, 'The "family member with a new number" scam. Always call the old number or ask a personal question first.', ['New-number story', 'Urgent money', 'Discourages calling']),
            $s('Website', 'Browser address bar', null, 'Online shop selling iPhones at 70% off, accepts only wallet transfer to a personal account.', 'https://iphone-sale-nepal.shop', true, 'Prices too good to be true and payment only to a personal account. No buyer protection.', ['Too-good price', 'Personal-account payment', 'Unknown shop']),
            $s('Email', 'noreply@sagarbank.com.np', 'Monthly e-statement', 'Your September statement is ready. Log in to internet banking through our website or app to view it. We will never ask for your password by email.', null, false, 'Correct domain, no link to click, tells you to use the channel you already trust.', []),
            $s('Social', 'Instagram DM from a stranger', null, 'Earn Rs 3,000 a day liking YouTube videos! Just pay Rs 1,000 joining fee to start the task group.', null, true, 'Task scams start with small payouts, then ask for bigger "deposits". Real jobs do not charge joining fees.', ['Joining fee', 'Easy money', 'Stranger DM']),
            $s('SMS', 'Hospital-Queue', null, 'Your token B-17 at City Hospital OPD will be called in about 10 minutes. Please be near counter 3.', null, false, 'A service update you expect, no link, no payment, no personal data requested.', []),
            $s('Call', 'Caller: "Nepal Telecom tower office"', null, 'Your SIM will be blocked in 1 hour for KYC. Press 1 and share your citizenship number and the code we SMS you.', null, true, 'Pressure plus a request for ID and a code. SIM KYC is done in person at an official outlet.', ['Time pressure', 'Asks for ID number', 'Asks for code']),
            $s('Email', 'tickets@himalayan-airways.com', 'Your boarding pass', 'Attached: boarding_pass.pdf.exe. Open to check in for your flight.', null, true, 'A ".pdf.exe" is a program disguised as a PDF. Never open unexpected executable attachments.', ['Double extension .exe', 'Unexpected attachment']),
            $s('Website', 'Wi-Fi login page', null, 'Free airport Wi-Fi. To connect, enter your email password.', null, true, 'Wi-Fi portals never need your email password. Use a different, random password or a mobile hotspot.', ['Asks for unrelated password', 'Public Wi-Fi']),
            $s('App', 'Phone settings', null, 'System update available from your phone maker. Install via Settings > Software update.', null, false, 'Updates delivered through the phone\'s own settings menu are safe and important to install.', []),
            $s('Email', 'ceo.office@gmail.com', 'Urgent and confidential', 'I am in a meeting. Buy 10 gift cards of Rs 5,000 each and send me the codes. Keep it secret.', null, true, 'CEO-fraud: a free email account, secrecy and gift cards. Verify by calling your boss directly.', ['Gift cards', 'Secrecy', 'Free email impersonating a boss']),
            $s('SMS', 'DHL-NP', null, 'Your parcel is held at customs. Pay Rs 150 clearance fee: https://dhl-np-delivery.info', 'https://dhl-np-delivery.info', true, 'Small "fees" are used to steal card details. Check the tracking number on the courier\'s official site.', ['Small fee bait', 'Look-alike domain']),
            $s('Website', 'Browser address bar', null, 'The e-library page of this app asks you to sign in with your Pratikshya account.', 'https://pratikshya.local/login', false, 'The address matches the service you are using and you navigated there yourself.', []),
            $s('Social', 'Friend\'s account on Messenger', null, 'Is this you in this video?? 😱 bit.ly/v1d30-you', 'bit.ly/v1d30-you', true, 'Hacked friend accounts spread shock-bait links that steal logins. Ask your friend via another channel.', ['Shock bait', 'Shortened link', 'Out-of-character message']),
            $s('Email', 'scholarship@tu-edu.org', 'Scholarship approved', 'You have been selected for a full scholarship. Send your bank PIN to receive the stipend.', null, true, 'No one needs your PIN to send you money. The domain is not the university\'s real .edu.np domain.', ['Asks for PIN', 'Unexpected reward', 'Wrong domain']),
        ];
    }
}
