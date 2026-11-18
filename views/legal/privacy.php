<?php

/** @var yii\web\View $this */

use yii\helpers\Url;

$this->title = 'Privacy Policy';
$this->params['description'] = 'How EntityIndex handles public-record data, account information and removal requests.';
$this->params['noindex'] = true;
$contact = Url::to(['/site/contact']);
$optOut = Url::to(['/opt-out/index']);
?>
<div class="container">
    <div class="page-head"><h1>Privacy Policy</h1></div>
    <div class="prose">
        <p class="updated">Last updated 18 November 2026</p>

        <p class="lead">This policy explains what information EntityIndex handles, where it comes from, how we use it and the choices you have. It covers two groups of people: individuals who appear in public business filings that we index, and customers and visitors who use our website, portal and API.</p>

        <h2>1. Public-record business data</h2>
        <h3>What we collect</h3>
        <p>We collect business registration records published by US state registries, such as Secretaries of State. These records describe businesses, and some of them name people connected to a business, such as registered agents, officers, managers, members and organizers, together with the addresses they gave the state.</p>
        <h3>Where it comes from</h3>
        <p>We use official sources only: open-data portals, bulk files published by states, and the public search pages of state registries. Each record keeps its state filing number, the time we collected it and a link back to the source.</p>
        <h3>What we never publish</h3>
        <p>We do not publish Social Security numbers, EINs, dates of birth, or personal phone numbers or email addresses gathered from sources other than the state filing. We do not combine filings with consumer data to build profiles of individuals.</p>
        <h3>How it is used</h3>
        <p>The data is used to provide a searchable index of businesses to our customers, including agents, lenders, accountants, service providers and data platforms. Customers agree in our <a href="<?= Url::to(['/legal/terms']) ?>">Terms of Service</a> to use it only for lawful business purposes and not to make decisions about individuals covered by the Fair Credit Reporting Act.</p>
        <h3>Who can see it</h3>
        <p>Filing details and registered agents are visible to signed-in accounts. Names, titles and addresses of officers and managers are shown only to signed-in paid accounts, and through the API and data files we license to customers. Company names and people are not shown on our public marketing pages.</p>

        <h2>2. Removing your information</h2>
        <p>If your name or home address appears on a company page, you can ask us to remove it using the <a href="<?= $optOut ?>">removal request form</a>. We confirm requests by email, usually within three business days. When we accept a request we hide the personal details of the people on that company from our website, API, exports and future data files.</p>
        <p>Removal from EntityIndex does not change the record at the state, which remains public. To change the state record, you can file an amendment or a change of registered agent with the state. If a new filing for the same company is published later, we will apply your earlier request to it.</p>

        <h2>3. Information about customers and visitors</h2>
        <h3>Account information</h3>
        <p>When you create an account we collect your name, email address and password (stored only as a one-way hash). We record the plan you are on, your usage against plan limits, your API keys (stored in hashed form) and your saved searches and exports.</p>
        <h3>Payment information</h3>
        <p>Payments are handled by our payment processor. We do not receive or store your full card number. We keep a record of your billing history, plan and the last digits and expiry of your card as provided by the processor.</p>
        <h3>Messages and forms</h3>
        <p>When you use our contact, waitlist or removal request forms we collect the details you enter, such as your name, email address, the plan you are interested in, and your message.</p>
        <h3>Technical information</h3>
        <p>Our servers log information about requests, such as IP address, browser type, pages and API endpoints requested, and the time of each request. We use these logs to run and secure the Service, enforce rate limits and investigate abuse.</p>
        <h3>Cookies</h3>
        <p>We use cookies that are needed for the site to work: a session cookie to keep you signed in, a cookie to protect forms against cross-site request forgery, and, if you choose it, a cookie that remembers your sign-in. We do not use advertising cookies or sell information to advertisers.</p>

        <h2>4. How we use customer information</h2>
        <ul>
            <li>to provide, maintain and secure your account and the Service;</li>
            <li>to apply plan limits, process payments and send receipts;</li>
            <li>to send service messages, such as saved-search alerts, account notices and changes to these policies;</li>
            <li>to answer your questions and handle removal requests;</li>
            <li>to tell you when paid plans open, if you joined the waitlist;</li>
            <li>to detect and prevent fraud, abuse and breaches of our terms;</li>
            <li>to comply with legal obligations.</li>
        </ul>
        <p>You can unsubscribe from any non-essential email using the link in the message.</p>

        <h2>5. Sharing</h2>
        <p>We do not sell customer account information. We share it only with service providers that help us run the Service, such as hosting, email delivery and payment processing, under contracts that limit their use of it; when required by law or to protect our rights and users; and with a buyer or successor if EntityIndex is involved in a merger, acquisition or sale of assets.</p>
        <p>Public-record business data, including the names and addresses of people listed in filings, is shared with our customers as part of the Service, subject to removal requests.</p>

        <h2>6. Retention</h2>
        <p>We keep account information while your account is open and for as long as needed afterwards to meet legal, tax and accounting obligations and to resolve disputes. Server logs are kept for a limited period for security purposes. Removal requests are kept so that we can continue to honor them. Public-record data is kept while it remains relevant to the index.</p>

        <h2>7. Security</h2>
        <p>We protect information with measures such as encrypted connections, hashed passwords and API keys, access controls and monitoring. No system is completely secure, and we cannot guarantee the security of information sent to us.</p>

        <h2>8. Your rights</h2>
        <p>Depending on where you live, including under the California Consumer Privacy Act and similar state laws, you may have the right to know what personal information we hold about you, to receive a copy, to correct it, to ask us to delete it, and to opt out of its sale or sharing. You will not be treated differently for using these rights.</p>
        <p>To make a request about your account information, contact us through the <a href="<?= $contact ?>">contact page</a> or from the email address on your account. To remove your details from public-record data, use the <a href="<?= $optOut ?>">removal request form</a>. We may need to verify your identity before acting on a request, and you may use an authorized agent where the law allows it.</p>

        <h2>9. Children</h2>
        <p>The Service is meant for businesses and is not directed at children. We do not knowingly collect information from anyone under 16 through our website or portal.</p>

        <h2>10. Changes to this policy</h2>
        <p>We may update this policy from time to time. We will post the new version on this page with a new date and, for material changes, notify account holders by email.</p>

        <h2>Contact</h2>
        <p>Questions about this policy or how we handle information can be sent through our <a href="<?= $contact ?>">contact page</a>, choosing the Data removal topic for anything about personal details in business records.</p>
    </div>
</div>
