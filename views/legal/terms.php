<?php

/** @var yii\web\View $this */

use yii\helpers\Url;

$this->title = 'Terms of Service';
$this->params['description'] = 'The terms that apply to using EntityIndex, its website, portal, API and data files.';
$this->params['noindex'] = true;
$contact = Url::to(['/site/contact']);
?>
<div class="container">
    <div class="page-head"><h1>Terms of Service</h1></div>
    <div class="prose">
        <p class="updated">Last updated 18 November 2026</p>

        <p class="lead">These terms govern your use of the EntityIndex website, customer portal, API, exports and bulk data files (together, the “Service”). By creating an account or using the Service you agree to them. If you use the Service on behalf of an organization, you agree on its behalf and confirm you have authority to do so.</p>

        <h2>1. The Service</h2>
        <p>EntityIndex collects business registration records published by US state registries, standardizes them and makes them searchable. Records include information such as legal name, entity type, status, filing number and date, registered agent, and officers, managers or members as published by the state. Each record links back to its official source.</p>
        <p>We may add, change or remove features, data fields, coverage or limits at any time. Where a change materially reduces a paid plan you have already paid for, we will tell you in advance and you may cancel and receive a pro-rated refund for the unused period.</p>

        <h2>2. Accounts</h2>
        <p>You must be at least 18 years old and provide accurate account information. You are responsible for keeping your password and API keys confidential and for all activity under your account. Tell us promptly through the <a href="<?= $contact ?>">contact page</a> if you believe your account or keys have been used without your permission.</p>
        <p>One account is for one person. Teams that need several users should contact us about a plan that covers them. We may suspend or close accounts that are shared, created in bulk, or used to get around plan limits.</p>

        <h2>3. Source data and accuracy</h2>
        <p>The data comes from public records maintained by state agencies. We do not create, verify or correct those records, and they can contain errors, omissions or outdated information. Filings can also be amended or withdrawn at the state after we index them. The Service is provided for information only and is not a substitute for checking the official state record, and nothing in it is legal, financial or credit advice.</p>
        <p>EntityIndex is an independent service and is not affiliated with, endorsed by, or acting for any state agency.</p>

        <h2>4. Plans, limits and fair use</h2>
        <p>Each plan includes limits on lookups, search history, exports, API calls and rate of requests, as shown on the <a href="<?= Url::to(['/pricing/index']) ?>">pricing page</a> and in your account. Limits reset on the schedule shown in your account. We may throttle or block requests that exceed your limits or that degrade the Service for other customers.</p>
        <p>You may not scrape the website or portal, access the Service through automated means other than the API, or use multiple accounts or keys to exceed your plan’s limits.</p>

        <h2>5. Licence to use the data</h2>
        <p>While your account is in good standing, we grant you a non-exclusive, non-transferable licence to use data obtained from the Service for your own internal business purposes, including contacting the businesses it describes in line with section 6.</p>
        <p>Unless you hold a Data license plan that expressly permits it, you may not sell, sublicense, publish, redistribute or otherwise make the data available to third parties, whether as a list, a database, a feed, or as part of a product or service that substantially reproduces it. Data license customers may redistribute only within the scope set out in their order.</p>
        <p>Records you exported while your account was active may be kept after your account ends, subject to these terms and to any removal request we pass on to you under section 7.</p>

        <h2>6. Acceptable use and outreach</h2>
        <p>You are responsible for how you use the data. You agree to comply with all laws that apply to your use, including the Telephone Consumer Protection Act, the CAN-SPAM Act, the Telemarketing Sales Rule, the National Do Not Call Registry, state telemarketing and data privacy laws, and fair lending and fair credit reporting laws where relevant.</p>
        <p>You will not use the Service or its data:</p>
        <ul>
            <li>to make eligibility decisions about individuals for credit, insurance, employment, housing or any other purpose covered by the Fair Credit Reporting Act;</li>
            <li>to harass, stalk, threaten or intimidate anyone, or to locate an individual for reasons unrelated to their business;</li>
            <li>to send unsolicited messages in breach of applicable law, or to place calls or texts to numbers on a do-not-call list without a lawful basis;</li>
            <li>for fraud, identity theft, impersonation, or to mislead anyone about who you are or your relationship with a state agency;</li>
            <li>to build or train a competing business registration database or service;</li>
            <li>to probe, test or breach the security of the Service, or to interfere with its operation.</li>
        </ul>

        <h2>7. Personal information and removal requests</h2>
        <p>Public business filings can include the names and addresses of people. We handle that information as described in our <a href="<?= Url::to(['/legal/privacy']) ?>">Privacy Policy</a>, and anyone can ask us to remove personal details from our site and API through the <a href="<?= Url::to(['/opt-out/index']) ?>">removal request form</a>. If you received data that has since been removed at a person’s request and we notify you, you agree to stop using that person’s details for outreach.</p>

        <h2>8. Fees and billing</h2>
        <p>Paid plans are billed in advance, monthly or annually, through our payment processor. Prices are in US dollars and exclude taxes, which we add where required. Your subscription renews automatically at the end of each billing period until you cancel. You can cancel at any time from your account; cancellation takes effect at the end of the current period and fees already paid are not refunded, except where these terms or the law say otherwise.</p>
        <p>We may change prices for future billing periods by giving you at least 30 days’ notice. If a payment fails, we may limit your account to the Free plan until it is resolved.</p>

        <h2>9. API keys and bulk files</h2>
        <p>API keys are tied to your account and may not be shared outside your organization. You must keep keys out of public code repositories and client-side applications. Download links for bulk files are for your account only and may not be shared or republished. We may rotate or revoke keys and links that appear to be exposed or misused.</p>

        <h2>10. Intellectual property</h2>
        <p>The underlying public records are not owned by us. The Service, including its software, design, standardized data structure, enrichments, compilations and documentation, is owned by EntityIndex and protected by intellectual property laws. Except for the licence in section 5, these terms do not grant you any rights in the Service.</p>
        <p>If you send us suggestions or feedback, we may use them without any obligation to you.</p>

        <h2>11. Suspension and termination</h2>
        <p>You may close your account at any time. We may suspend or terminate your access, with or without notice, if you breach these terms, fail to pay, use the Service in a way that exposes us or others to legal risk, or if we are required to do so by law. Sections 3, 5 (as to retained data), 6, 10, 12, 13, 14 and 16 survive termination.</p>

        <h2>12. Disclaimers</h2>
        <p>The Service and all data are provided “as is” and “as available”. To the fullest extent permitted by law, we disclaim all warranties, express or implied, including warranties of accuracy, completeness, timeliness, merchantability, fitness for a particular purpose and non-infringement. We do not warrant that the Service will be uninterrupted or error-free, or that any target for data freshness will be met.</p>

        <h2>13. Limitation of liability</h2>
        <p>To the fullest extent permitted by law, EntityIndex will not be liable for any indirect, incidental, special, consequential or punitive damages, or for lost profits, revenue, data or business opportunities, arising from or related to the Service, even if we were told they were possible. Our total liability for all claims relating to the Service is limited to the greater of the fees you paid us in the 12 months before the event giving rise to the claim, or one hundred US dollars.</p>

        <h2>14. Indemnity</h2>
        <p>You will defend and indemnify EntityIndex against any claims, damages, fines and costs, including reasonable legal fees, arising from your use of the data, your outreach to businesses or individuals, or your breach of these terms or of applicable law.</p>

        <h2>15. Changes to these terms</h2>
        <p>We may update these terms from time to time. We will post the new version on this page with a new date and, for material changes, notify account holders by email at least 14 days before they take effect. Continuing to use the Service after that date means you accept the updated terms.</p>

        <h2>16. General</h2>
        <p>These terms are governed by the laws of the United States and of the state in which EntityIndex is organized, without regard to conflict of law rules, and disputes will be resolved in the courts located there. If any part of these terms is found unenforceable, the rest remains in effect. Our failure to enforce a provision is not a waiver. You may not assign these terms without our consent; we may assign them in connection with a merger, acquisition or sale of assets. These terms, together with any order form for a Data license, are the entire agreement between you and us about the Service.</p>

        <h2>Contact</h2>
        <p>Questions about these terms can be sent through our <a href="<?= $contact ?>">contact page</a>.</p>
    </div>
</div>
