# Early Warning Inbox - User Guide

## What is the Early Warning Inbox?

The **Early Warning** page lists every day on which a section used too much of its daily overtime budget. Each row is one section on one day, with its zone (**Kuning** or **Merah**), the index, how many working days in a row it has been in alert, and whether a follow-up has already been written. Use it to see which sections need action and which are already handled.

## Who Can Use It?

| Role                | What they see                                                   |
| ------------------- | --------------------------------------------------------------- |
| **Administrator**   | Alerts of every department, and can pick one department         |
| **Manager**         | Alerts of their own department only (department cannot change)  |
| **Team Leader**     | Alerts of their own department only (department cannot change)  |
| **Operator / User** | No access                                                       |

## How to Use

### Read the list

1. Open the **Early Warning** menu in the sidebar
2. Four numbers appear at the top: **Total alerts**, **Merah**, **Kuning** and **Without follow-up**
3. Below them, each alert shows the section and department, the date, the zone, the index in percent, the streak (for example "3 consecutive days") and the follow-up status:
    - **Not followed up yet**: nobody has started a follow-up
    - **Draft**: a follow-up is saved but not sent
    - **Terkirim**: the follow-up was sent by email
4. The most serious alerts come first: Merah before Kuning, then the longest streak, then the newest date

### Filter the list

1. Pick a **Department** (Administrators only) and then a **Section**
2. Change the **Date range**. By default the page shows the last 7 days up to today. The range can be at most 93 days
3. Click a zone button (**All zones**, **Kuning**, **Merah**) to narrow the list
4. Click a status button (**All statuses**, **Not followed up yet**, **Draft**, **Terkirim**) to see only alerts in that state
5. Click **Reset** to return to the default view

> 💡 **Tip:** The page address changes with your filters. Copy it to share exactly the same view with a colleague, or bookmark it.

> 💡 **Tip:** The four numbers at the top cover the department, section and dates you chose. The zone and status buttons only narrow the list below them.

### Open an alert

1. Click anywhere on a row, or click **Follow Up**
2. The alert detail page opens, where you can review the situation and write the follow-up

### Browse many alerts

1. With more than 25 alerts, the list is split into pages
2. Use the arrow buttons under the list to move between pages

### On a phone

On a narrow screen every alert is shown as a card instead of a table, with the same information and the same **Follow Up** button.

## Frequently Asked Questions (FAQ)

**Q: Why is the list empty?**
A: Either no section went over its budget in the chosen period (good news), or a filter is too narrow. Widen the date range or click **Reset**.

**Q: Why can I not change the department?**
A: Managers and Team Leaders only work with their own department. Only Administrators can choose a department.

**Q: Why is the Section list empty?**
A: Administrators must choose a department first. The sections of that department then appear.

**Q: Why does the page say the filters are invalid?**
A: The address contained a value the page does not understand (for example a wrong date or a range longer than 93 days). You are returned to the default view.

**Q: Why does a section not appear even though it had a busy day?**
A: A section without an overtime budget for that month is not evaluated, and a day in the Hijau zone is not an alert.

## Troubleshooting

| Issue                                       | Solution                                                                                         |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| Red message under the dates                 | The start date is after the end date, or the range is too long. Correct the dates; nothing changes until they are valid |
| The numbers at the top show a loading bar   | The alerts are still being calculated; they appear within a moment                               |
| "No alerts in this period" but I expected some | Widen the date range, choose **All zones** and **All statuses**, or click **Reset**           |
| Access Restricted page                      | You do not have the Administrator, Manager or Team Leader role                                   |
