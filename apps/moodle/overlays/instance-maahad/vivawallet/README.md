# Moodle Dynamic Semester-Based Payment Integration Guide

### Pure PHP Architecture (Zero-Dependency Framework)

This documentation outlines the complete, step-by-step configuration required to
establish a secure, reproducible, and dynamically priced semester payment
pipeline using native Moodle PHP files.

Administrators gain absolute control over pricing, cohort routes, and paywall
visibility directly from the standard Moodle GUI. This architecture uses an
open-door Master Course for paywall hosting and leverages direct **Cohort
Syncing** on sub-courses to guarantee that students cannot bypass the paywall.

---

## 0. Prerequisites: Global Plugin Activation

By default, clean Moodle installations leave advanced enrollment modules
deactivated site-wide. Before configuring your courses, you must explicitly
enable the cohort synchronization engine.

1. Log in to Moodle as an **Administrator**.
2. Navigate to **Site administration** > **Plugins** > **Enrolments** > **Manage enrol plugins**.
3. Locate **Cohort sync** in the grid.
4. Look at the **Enable/Disable** column and click the **eye icon** to open it (changing its state from grayed out/slashed to active).
5. Ensure **Self enrolment** and **Manual enrolments** are also enabled (they usually are by default).

---

## 1. Course Architecture: Gateway & Sub-Course Isolation

To prevent students from gaining unauthorized access to the entire semester when
checking the paywall, the Master Course is decoupled from sub-course enrollment
triggers. The **Cohort** acts as the singular source of truth for academic
access.

* **Master Gateway Course:** Acts as a public landing page. Students can self-enroll here for free to view the paywall, but the rest of the semester remains completely hidden.
* **Sub-Courses:** Locked behind direct cohort synchronization. They only unlock the moment a verified payment updates the student's cohort status.

### Step 1.1: Configure the Master Gateway Course (The Open Door)

1. Navigate to **Site administration** > **Courses** > **Manage courses and categories**.
2. Create a category representing your active tier (e.g., `Semester 1`).
3. Inside this category, create your **Master Gateway Course** (e.g., `Semester 1 - Master Gateway & Orientation`).
4. Open the course dashboard, navigate to **Participants**, and open the **Enrolment methods** panel.
5. If **Self enrolment (Student)** is not already listed, select it from the *Add method* dropdown. Leave its settings at their defaults (no enrollment key required) and save.

### Step 1.2: Connect Sub-Courses Directly to the Cohort

Execute these steps inside **each individual sub-course classroom space** (e.g.,
separate classrooms for Math, Science, and Language tracks) belonging to this
semester:

1. Open the sub-course space.
2. Navigate to **Participants** > **Enrolment methods**.
3. If there are any pre-existing *Course meta links* pointing to the Master course, **delete them completely** to prevent access leaks.
4. From the *Add method* dropdown selection element, choose **Cohort sync**.
5. Configure the synchronization engine instance parameters:
* **Custom instance name:** `Semester 1 Academic Access`
* **Search cohort:** Select your semester cohort (e.g., `Semester 1 Access`).
* **Assign role:** `Student`


6. Click **Add method**.

---

## 2. Moodle Global Configuration (`config.php`)

To securely centralize payment keys without exposing financial secrets inside
your public repository files, append your Viva Wallet configuration constants
natively to the bottom of Moodle's configuration framework.

1. Access your persistent storage volume block or open the terminal inside your running Moodle server environment.
2. Open `/opt/bitnami/moodle/config.php` with a text editor.
3. Append the following parameters near the bottom of the file, **directly above** the `require_once(__DIR__ . '/lib/setup.php');` core boot execution line:

```php
// Viva Wallet Configuration
$CFG->viva_merchant_id   = 'YOUR_MERCHANT_ID';
$CFG->viva_api_key       = 'YOUR_API_KEY';
$CFG->viva_client_id     = 'YOUR_SMART_CHECKOUT_CLIENT_ID';
$CFG->viva_client_secret = 'YOUR_SMART_CHECKOUT_CLIENT_SECRET';
$CFG->viva_source_code   = '5291';
$CFG->viva_api_url       = 'https://api.vivapayments.com';
$CFG->viva_webhook_key   = 'YOUR_WEBHOOK_VERIFICATION_KEY';
$CFG->viva_mock_mode     = false;
```

4. Save and exit the file.

---

## 3. Admin GUI Setup: Course Custom Fields

This configuration provisions explicit user interface data fields on the course
editing dashboard, allowing non-technical operators to control pricing and
cohort routing directly.

1. Navigate to **Site administration** > **Courses** > **Course custom fields**.
2. Click **Add a new category** and name the grouping: `Semester Payment Configurations`.
3. Inside this newly created category canvas, click **Add a new custom field** and select **Short text**. Configure it exactly:
* **Name:** `Semester Price`
* **Short name:** `semesterprice` *(Must be lowercase, no spaces)*

4. Click **Add a new custom field** again and select **Short text**. Configure it exactly:
* **Name:** `Mapped Cohort ID`
* **Short name:** `mappedcohortid` *(Must be lowercase, no spaces)*

5. Save your layout modifications.

---

## 4. Cohorts & Master Course Alignment

This maps your Master Gateway Course to the fulfillment track. While sub-courses
link directly to the cohort for *enrollment*, the Master Course links to it
strictly to manage *paywall button visibility via an automated group*.

### Step 4.1: Create the Semester Cohort

1. Navigate to **Site administration** > **Users** > **Accounts** > **Cohorts**.
2. Click **Add new cohort** and define its parameters:
* **Name:** `Semester 1 Access`
* **Cohort ID:** `semester-1`

3. Save changes. On the main Cohorts management dashboard, locate the specific
   **internal database integer ID** of your new cohort (visible by inspecting
   the edit icon link address string, e.g., `id=3`). Note this number.

### Step 4.2: Configure the Master Gateway Course Settings

1. Navigate to **Site administration** > **Courses** > **Manage courses and categories**.
2. Open the **Edit settings** interface for your **Master Gateway Course**.
3. Scroll to the bottom, expand the *Semester Payment Configurations* section, and input your business variables:
* **Semester Price:** `1500.00` *(Type standard decimal format using local DKK valuations)*
* **Mapped Cohort ID:** `3` *(Input the database integer ID noted in step 4.1)*

4. Save changes. Open the Master Gateway Course dashboard page and note its unique **Course ID** from the browser address bar (e.g., `id=12`).

### Step 4.3: Activate the Automated Group Sync on the Master Course

1. Inside the **Master Gateway Course** space, navigate to **Participants** and open the **Enrolment methods** configuration view.
2. From the *Add method* dropdown list element, choose **Cohort sync**.
3. Configure the synchronization parameters exactly as shown to trigger native group generation:
* **Custom instance name:** `Semester 1 Payment Sync`
* **Active:** `Yes`
* **Search cohort:** Select your cohort (`Semester 1 Access`).
* **Assign role:** `Student`
* **Add to group:** **Create new group** *(Crucial: This generates an automated course group named exactly after your cohort to handle button hiding rules)*.

4. Click **Add method**.

---

## 5. Repository Files Deployment

Deploy the integration infrastructure scripts out of your code repository
directly into your functional persistent container framework storage layers.

1. Locate your local payment integration scripts folder inside your repository.
2. Provision a dedicated custom directory path inside Moodle's system layout:

```bash
mkdir -p /opt/bitnami/moodle/local/vivawallet/
```


3. Copy your integration assets from your source repo directly into this workspace target:
* `/opt/bitnami/moodle/local/vivawallet/gateway.php`
* `/opt/bitnami/moodle/local/vivawallet/webhook.php`
* `/opt/bitnami/moodle/local/vivawallet/simulator.php`

4. Ensure files retain execution permissions matching standard Bitnami
   parameters (`664` application directory permission schemes with
   corresponding web server daemon owner mappings).

---

## 6. Viva Wallet Webhook Integration

To authorize the payment automation gateway to run fulfillment routines
asynchronously, you must register your integration endpoint inside the banking
system dashboard.

1. Log in to your **Viva Wallet Banking Portal** (or your active sandbox demo merchant dashboard account).
2. Navigate to **Settings** > **API Access** > **Webhooks**.
3. Click **Create Webhook** (or Edit) and set the tracking parameters:
* **URL:** `https://akademi.maahad.dk/local/vivawallet/webhook.php`
* **Event Type:** Check the box for `Transaction Payment Created` *(Event Code ID 1796)*.
* **Active:** Toggle to `True / Enabled`.

4. Click Save. Copy the verification challenge token string generated by the
   terminal interface and save it as the value for your `$CFG->viva_webhook_key`
   variable inside `config.php`.

---

## 7. Universal Paywall Button & Conditional Visibility

To prevent paid students from seeing a confusing billing request after checking
out, wrap your universal button in Moodle's native **Restrict Access** engine
using the auto-synchronized course group created in Step 4.3.

### Step 7.1: Add the Dynamic Paywall Block

1. Open your **Master Gateway Course** dashboard and toggle **Editing mode** on.
2. Add a new **Text and media area** resource (formerly a Label) or a custom **HTML Block** at the prominent top section of the course grid page.
3. Switch your Moodle text editor interface to raw source code access mode
   (`HTML / </>`) and drop in this block. Replace `id=12` inside the link
   reference with the explicit current Moodle Database Course ID noted in step
   4.2:

```html
<div class="semester-paywall-container" style="text-align: center; margin: 30px 0; padding: 25px; border: 2px dashed #007bff; border-radius: 8px; background-color: #f8f9fa;">
    <h3 style="color: #222; margin-bottom: 8px; font-weight: 600;">Unlock Full Semester Access</h3>
    <p style="color: #555; margin-bottom: 20px; font-size: 1rem;">Gaining access unlocks this introductory dashboard along with every associated subject and sub-course classroom instantly using Viva Wallet.</p>
    
    <a href="/local/vivawallet/gateway.php?id=12" class="btn btn-success btn-lg" style="background-color: #28a745; border-color: #28a745; padding: 14px 35px; font-size: 1.2rem; font-weight: bold; border-radius: 6px; color: #fff; text-decoration: none; display: inline-block; box-shadow: 0 4px 6px rgba(40,167,69,0.2); transition: all 0.2s ease;">
        Proceed to Secure Payment
    </a>
</div>
```

### Step 7.2: Apply Visibility Firewalls via Native Groups

1. While still inside the configuration settings of the paywall block you just created, scroll down and expand the **Restrict access** section.
2. Click **Add restriction...** and choose **Group** from the native modal choices.
3. Apply these constraints exactly:
* Change the dropdown logic operator from "must" to **must not**.
* Select your target synced group: **Semester 1 Access-group** *(This group is automatically populated by your Cohort Sync engine settings)*.

4. **Crucial Rule:** Look directly at the **eye icon** located on the immediate
   left margin of your restriction constraint. Click it so that a slash mark
   covers it (**Hidden entirely if user does not meet conditions**). This stops
   Moodle from showing a grayed-out, unlocked placeholder warning box to active
   students.

### Step 7.3: Add a Success Banner (Optional UX Polish)

To provide a clean confirmation message once a student returns from checkout:

1. Create a second **Text and media area** directly beneath your paywall block.
2. Inject a clean HTML layout:

```html
<div class="semester-success-container" style="text-align: center; margin: 30px 0; padding: 25px; border: 2px solid #28a745; border-radius: 8px; background-color: #d4edda; color: #155724;">
    <h3 style="font-weight: 600; margin-bottom: 5px;">Velkommen til Semester 1!</h3>
    <p style="margin: 0; font-size: 1rem;">Din betaling er godkendt. Du har nu fuld adgang til dette introforløb samt alle tilknyttede fagsider herunder.</p>
</div>
```

3. Expand **Restrict access**, select **Add restriction...** > **Group**.
4. Set the condition parameters to: Student **must** belong to **Semester 1 Access-group**.
5. Click the **eye icon** to slash it (**Hidden entirely for unpaid users**).
6. Save and exit. Unpaid students will see only the paywall button, which
   transforms seamlessly into your confirmation layout the exact millisecond the
   backend fulfillment webhook adds them to the cohort.
