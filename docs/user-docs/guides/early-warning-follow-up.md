# Early Warning Follow-Up (5W2H) - User Guide

## What is the follow-up?

When a section turns Kuning or Merah, the **Early Warning** page lets you record what will be done about it. The follow-up is a short 5W2H sheet: **What** happened, **Why**, **When**, **Where**, **Who** is responsible (the PIC department), **How** it will be handled, and **How much** it costs. The system fills in what it already knows from the alert, so you only review, edit and send. Sending emails the PIC department. Nothing else happens: the follow-up is a notice and a record, not an approval.

## How to Use

### Open the form

1. Open the **Early Warning** menu in the sidebar.
2. Click an alert, or its **Tindak Lanjut** button.
3. Scroll to **Tindak Lanjut Alert (5W2H)** below the chart.

The form opens already filled. A small tag shows where each text comes from:

- **Dibuat sistem**: the text is still what the system wrote.
- **Diubah**: you changed it.

Fields the system could not fill yet (for example **Why** and **How**) stay empty for you to type.

### Edit and reset

1. Change any field.
2. To go back to the system text, click **Reset ke saran sistem** next to the field, or **Reset semua ke saran sistem** at the bottom for all fields. You can only reset while the follow-up is a draft.

### Save a draft

1. Fill in at least **What**.
2. Click **Simpan Draft**.

You can come back and edit the draft as often as you like. An alert has one draft at a time.

### Send by email

1. Fill in **What**, **Why**, **When**, **Who** and **How**.
2. Choose the PIC department in **Who**. Every active Admin, Manager and Team Leader of that department gets the email.
3. Click **Kirim via Email**.

The status changes to **Terkirim** and the follow-up becomes read-only. The email shows the section, date, zone, index, streak, the 5W2H, the cost in Rupiah, who sent it, and a link back to the alert.

### Write another follow-up

When the latest follow-up has been sent, click **Tindak Lanjut Baru** to start a new one from the system text. Earlier follow-ups stay in the history chips above the form; click a chip to read it.

> 💡 **Tip:** If nothing was sent after you clicked **Kirim via Email**, read the message under the form: usually the chosen department has no active recipient, or **Why** / **How** is still empty. Nothing is saved or sent in that case.

## Frequently Asked Questions (FAQ)

**Q: Why is the form missing on some alerts?**
A: The page shows "No alert on this date" when the index is back in the Hijau zone, the section has no budget, or the date is in the future. No follow-up can be written for such a date.

**Q: Can I edit a follow-up after sending it?**
A: No. A sent follow-up is read-only. Use **Tindak Lanjut Baru** to record a new one.

**Q: The index in my follow-up differs from the chart.**
A: The follow-up stores the index, zone and streak of the alert at the moment you saved or sent it. The system recalculates them every time you save, so you cannot type them.

**Q: Where did the follow-up panel on the Dashboard go?**
A: It lives only under **Early Warning** now. The Early Warning chart stays on the Dashboard.

## Troubleshooting

| Issue                                                       | Solution                                                                                            |
| ----------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| "The chosen department has no active recipients"            | Choose another department in **Who**, or ask an Admin to activate a Manager or Team Leader there.   |
| "This alert already has a draft"                            | Another tab or colleague saved a draft first. Reload the page and edit that draft.                  |
| "A follow-up can only be saved for a date that is an alert" | The alert has cleared since you opened the page. Go back to **Early Warning** and refresh the list. |
| The email does not arrive                                   | In production the mail server and the queue worker must be running; ask your administrator.         |
