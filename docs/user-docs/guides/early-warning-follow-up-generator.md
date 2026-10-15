# Early Warning Follow-Up Written by the System - User Guide

## What is it?

When you open an alert in **Early Warning**, the follow-up form is already **filled in by the system**: What, Why, When, Where, Who, How and How much. You only read it, change what you disagree with, and save a draft or send it by email. Every sentence in "Why" and "How" shows the number it comes from, for example "Category Production holds 78% of the overtime hours".

## How to Use

### Review what the system wrote

1. Open the **Early Warning** menu in the sidebar
2. Click an alert row, or click **Follow Up**
3. Scroll to **Tindak Lanjut Alert (5W2H)**. Read each field:
    - **What**: the index of the day, the zone, how far it is above the limit, and for how many days in a row
    - **Why**: the main cause, plus up to two supporting causes, each with its figure
    - **When**: the alert date. Under it you see the whole period, for example "12–13 Oct 2026 (2 consecutive working days)"
    - **Where**: department and section
    - **Who**: the department that should act. It follows the cause (see below), otherwise your own department
    - **How**: the suggested action for the main cause, with the numbers
    - **How much (Rp)** and **How much (hours)**: the estimated overtime hours and cost for the follow-up period
4. A small **System generated** tag sits next to each field the system wrote

### Change or reset a field

1. Type over any field. The tag changes to **Edited**
2. To go back, click **Reset to system suggestion** next to the field, or **Reset all to system suggestion** at the bottom

### Save or send

1. Click **Save Draft** to keep it (only "What" is needed)
2. Click **Send by Email** to notify the PIC department. Why, When, Who and How must be filled in, and the department needs at least one active Admin, Manager or Team Leader with an email

> 💡 **Tip:** If the cost shows 0 with a yellow note "Cost estimate not available yet", the system had no hourly rate to work with. Type the cost yourself.

### Choose the PIC department of each cause (Admin only)

1. Open **Administration** in the sidebar and stay on the **Policies** tab
2. Scroll to **PIC per Cause**
3. For each cause, pick the department that should be "Who", or keep **Own department of the alert**
4. Untick **Active** to ignore a cause's PIC
5. Click **Save**

The change only applies to suggestions made afterwards. Follow-ups that are already saved or sent keep what they had.

## Frequently Asked Questions (FAQ)

**Q: Is this an AI?**
A: No. The system follows fixed rules (for example "one category holds at least half of the overtime hours") and shows the figure behind each sentence. The same alert always gives the same text.

**Q: Why does "Why" say the cause is not yet identified?**
A: None of the rules found a clear cause in the data. Write the reason yourself, or investigate first.

**Q: Why is the cost 0 and the hours 0?**
A: The overtime of those days did not go above the daily target (the alert came from the 70% / 90% limits), so there is no extra overtime to estimate.

**Q: Why is "Who" my own department when I expected another one?**
A: No PIC is set for that cause, it is switched off, or the chosen department has nobody who can receive the email. An Admin can change it in **Administration**.

**Q: I edited the form and then reopened the alert. Did the text change?**
A: A saved draft keeps your text. The suggestion is only used for an alert that has no draft yet.

## Troubleshooting

| Issue                                             | Solution                                                                                                  |
| ------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| Fields are empty on a new alert                   | The date is not an alert (Green). Only Kuning and Merah days get a follow-up                              |
| Send fails with "no active recipients"            | Choose another department in **Who**, or ask an Admin to add an active Manager or Team Leader to this one |
| The Why text mentions "vs September 2026" figures | Volume and manpower are monthly plant figures. They show a trend, not what happened on the alert day      |
| The PIC card is missing in Administration         | Only Admins see **Administration**. Open the **Policies** tab                                             |
