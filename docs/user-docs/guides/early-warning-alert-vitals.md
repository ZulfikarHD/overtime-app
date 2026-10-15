# Early Warning Alert Vitals - User Guide

## What are the vitals?

On the alert page, two small cards help you understand **why** a section used so much overtime: **Production volume** and **Manpower change**. Each shows how much the figure changed compared with the previous month, with an arrow and the month it is compared with, for example **+20%** and "vs September 2026".

## How to Use

### Read the vitals

1. Open the **Early Warning** menu in the sidebar
2. Click an alert row, or click **Follow Up**
3. Under the index bar, read the two cards:
    - **Production volume**: the change in vehicles planned for the month of the alert, compared with the month before
    - **Manpower change**: the change in the total number of people in the plant, compared with the month before. The card says "(plant-wide)" ("seluruh pabrik" in Indonesian) because it is the whole plant, not only your section
4. The arrow points up when the figure grew, down when it fell, and is flat when it did not change

> 💡 **Tip:** A volume that grew while manpower did not is a common reason for extra overtime. Use it when you write the **Why** of the follow-up.

### Actual or Forecast badge

Next to the period, a small badge tells where the number comes from:

- **Actual** (green): the figures entered in ML Data Training
- **Forecast** (amber, dashed card border): no actual figure is entered yet, so the planned figure from Forecasting is used. It is an estimate. Enter the actual figures in ML Data Training to replace it

## Frequently Asked Questions (FAQ)

**Q: Why does it say "vs September 2026" and not "last 7 days"?**
A: Production volume is only recorded per month. The page shows the real period instead of an estimate.

**Q: Why does a card show N/A?**
A: The month of the alert or the month before has no data yet, or the earlier figure was zero so a percentage cannot be calculated. Hover over the information icon to read the reason. The page never fills in an estimate.

**Q: Is Manpower change the number of people in my section?**
A: No. It is the plant-wide total. A section can lose or gain people without moving this number.

**Q: A card is green or red. Is the change good or bad?**
A: The cards stay neutral on purpose. A change is information to help you judge the alert, not a verdict.

## Troubleshooting

| Issue                                  | Solution                                                                                                                     |
| -------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| Both cards always show N/A             | The monthly production and manpower data of the alert month and the month before have not been entered. Ask an Administrator |
| The number does not match my own count | The cards use monthly plant data. Your section head count and daily output are not part of it                                |
