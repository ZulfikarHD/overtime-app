# Daily Burn Chart Index & Section Comparison - User Guide

## What is Daily Burn Chart Index & Section Comparison?

The **Daily Burn Chart Index** and **Section Burn Comparison** are interactive analytics tools displayed on your main **Dashboard**. They provide plant managers, supervisors, and team leaders with real-time visual tracking of overtime hours consumed throughout the month compared against planned budget ceilings.

- **Daily Burn Line Chart**: Shows daily cumulative overtime hours as a curve progressing from day 1 to the end of the month, comparing actual approved hours against the planned pace and predicting month-end trajectory.
- **Section Burn Comparison**: Ranks all production sections from highest to lowest overtime consumption, highlighting sections operating within safe limits or approaching budget overruns.

---

## How to Use

### 1. Reading the Daily Burn Line Chart

1. Open the **Dashboard** menu from the application sidebar.
2. Locate the **Daily Burn Chart Index Overtime** card positioned right below the four header metric cards.
3. Observe the three distinct line series:
    - **Cumulative Plan (Dashed Blue Line)**: Represents the linear budget curve distributed evenly across all days of the month.
    - **Approved Actual (Solid Colored Line)**: Shows the total approved overtime hours accumulated up to the current day. The line color changes based on status (Green for Safe, Blue for On Track, Amber for Warning, Red for Critical Overrun).
    - **AI / ML Projection (Dotted Violet Line)**: Displays the forecasted month-end trajectory based on current velocity and historical patterns.
4. Check the **Budget Ceiling (Plafon Anggaran)** dashed line with the soft red shaded zone above it. If your actual line crosses above this threshold, the department is currently exceeding planned budget hours.
5. Hover over any date on the chart to view the detailed tooltip:
    - Specific operational date
    - Cumulative approved hours
    - Cumulative planned hours
    - Variance (hours ahead of or below target pace)

> 💡 **Tip:** Check the quick stats strip in the card header for quick figures on **Budget Ceiling**, **Current Realization**, **Remaining Hours**, and **Burn Index %**.

---

### 2. Navigating Months and Filtering by Section

1. To view past or future months, click the **Previous Month (`←`)** or **Next Month (`→`)** arrows in the top right corner of the chart header.
2. To isolate a specific production section, click the section dropdown menu (next to the month arrows) and select the desired section name.
3. To return to viewing the entire department, select **All Sections (Department)**.

---

### 3. Comparing Sections and Drilling Down

1. Scroll down to the **Section Burn Comparison** card directly beneath the daily burn chart.
2. View the horizontal bar chart displaying all active production sections sorted from highest burn index percentage to lowest:
    - 🟢 **Green (< 85%)**: Safe budget consumption.
    - 🔵 **Blue (85% – 100%)**: On track within target budget.
    - 🟡 **Amber (101% – 115%)**: Warning, approaching or slightly exceeding planned hours.
    - 🔴 **Red (> 115%)**: Critical deficit requiring immediate supervisory intervention.
3. **Drill Down to Section Details**:
    - Click on any bar in the chart, or click on any section card in the quick-access grid below the chart.
    - You will be automatically redirected to the **Budget Burn Index** page filtered to that section's weekly burndown schedule.

---

## Frequently Asked Questions (FAQ)

**Q: Why does the actual realization line stop at today's date instead of reaching the end of the month?**  
A: Overtime hours are only recorded as actual realization after shift completion and formal manager approval. Future calendar days are unapproved, so the dotted violet projection line forecasts expected trajectory instead.

**Q: Why can't I select sections from other departments?**  
A: In accordance with plant data security standards, Department Managers and Team Leaders can only view sections within their authorized organizational scope. System Administrators have plant-wide viewing permissions.

**Q: What causes the red shaded zone on the daily chart?**  
A: The red shaded zone marks all hours exceeding 100% of the allocated monthly budget ceiling. When the realization curve enters this area, the department has depleted its entire monthly overtime quota.

---

## Troubleshooting

| Issue                                                             | Solution                                                                                                                                                                   |
| ----------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Daily chart displays "No data available" (Belum ada data)         | Ensure that the selected department or section has an active overtime budget allocated for the chosen month in **Budget Planning**.                                        |
| Overtime hours worked yesterday do not appear in the actual curve | Overtime hours must be approved by the Department Manager before they are reflected in cumulative actual totals. Unapproved drafts or pending submissions are not counted. |
| Section Comparison bar click does not open the detail page        | You can also click the dedicated section card in the list below the chart to navigate to the section's weekly breakdown.                                                   |
