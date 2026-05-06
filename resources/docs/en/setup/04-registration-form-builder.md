---
title: Customizing the registration form
group: Setup
order: 4
---

# Customizing the registration form

Every tournament collects different info from players. Some want age and district; others want a guardian's signature; others want proof of vaccination. AuctionBall's **Form builder** lets you add anything you need without writing a single line of code — just drag, drop, and save.

## Open the Form builder

1. Click **Seasons** in the left sidebar
2. Find the season you want to customize
3. Click the **Form builder** button on its row

The builder panel opens right there. The left side shows your form preview as players will see it; the right side shows controls for each field.

## What's already on the form (you can't remove these)

These fields are always there — every tournament needs them:

- Full name
- Photo (300×300, cropped automatically)
- Category (Elite / Regular / New)
- Position
- Jersey number
- Profession
- Batting style and bowling style (cricket only)

Everything else is up to you.

## Adding a new field

At the bottom of the builder, click any field type to add it:

| Type | Use it for | Example label |
|---|---|---|
| **Section header** | A heading to group related fields. No input. | "Contact information" |
| **Short text** | A single line of text | "Father's name" |
| **Paragraph** | Multi-line text | "Tell us about your sports background" |
| **Number** | Numbers only | "Years of experience" |
| **Email** | Auto-validates the email format | "Your email address" |
| **Phone** | Bangladeshi phone number | "Mobile number" |
| **URL** | Web links | "Facebook profile" |
| **Date** | A calendar picker | "Date of birth" |
| **Time** | A time picker | "Preferred match time" |
| **Dropdown** | Pick one from a list | "District" |
| **Radio** | Pick one (all options visible) | "T-shirt size: S/M/L/XL" |
| **Multi-select checkbox** | Pick all that apply | "Equipment you own" |
| **Yes/No checkbox** | A single agreement | "I have read the rules" |
| **Image upload** | A photo or document image | "National ID photo" |
| **Payment** | A combined block: bKash/bank cards + transaction ID input | "Pay registration fee" |

After clicking, the new field appears at the end of your form preview.

## Reordering and editing fields

- **Drag a field** by its handle (the ⋮⋮ icon at the top-left of each field card) to move it up or down
- **Click the label** to rename it
- **Click the placeholder text** to change the example shown inside the input
- Toggle **Required** to force players to fill it in before submitting
- For dropdowns / radios / multi-select, click **Add option** to extend the choice list

## Conditional fields (show only when…)

You can hide a field unless something else is set. Example: only ask for **Visa number** if **Are you traveling internationally?** is "Yes".

1. Click the field that should appear conditionally
2. Toggle **Conditional** on
3. Pick the source field (the question that controls visibility)
4. Pick the operator: equals, not equals, is filled, or is empty
5. Type the value to compare against (for equals / not equals)

The field stays hidden on the form until the condition is true.

## Saving

When you're happy with the form, click **Save form** at the bottom of the builder. Your changes go live immediately — anyone who opens your registration link from then on sees the updated form.

You can come back any time and add, remove, or reorder fields.

## Tips for a good form

- **Don't over-ask.** Every extra field drops the percentage of people who finish. Aim for 8 custom fields or fewer.
- **Put payment last.** Players who pay first feel committed and are more likely to finish.
- **Use section headers** — group your fields under "Contact", "Sports background", "Payment" so the form feels organized.
- **Phone is more reliable than email** in Bangladesh. If you can only ask for one, ask for phone.
- **Image fields** can be 600×600 by default, or you can change the crop size when you add the field.

[Next: Inviting your team →](/help/roles-and-permissions)
