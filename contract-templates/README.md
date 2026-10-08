# Contract Templates Guide

## Overview

This folder contains the DOCX contract templates used for generating employment contracts for different locations.

## Available Templates

One appointment letter per campus. The **campus chosen on the Issue Contract form decides the letter** (it also has that campus's letterhead and Location line):

| Campus on the form                  | Template file          |
| ----------------------------------- | ---------------------- |
| Lincoln University, Kumo Campus     | `Gombe_Template.docx`  |
| Lincoln College, Abuja Campus       | `Abuja_Template.docx`  |
| Lincoln University, NSUK Campus     | `Keffi_Template.docx`  |

The Duties section and the other fixed clauses in each letter are part of the template - edit the .docx in Word to change them.

## Template Placeholders

To enable automatic data replacement, use the following placeholders in your DOCX templates. These placeholders will be replaced with actual values when generating contracts.

### Available Placeholders

These are the placeholders both templates now use. Type them exactly, in one piece, in Word (use `${name}`, not `$ {name}`).

| Placeholder        | Description                                  | Example Value                         |
| ------------------ | -------------------------------------------- | ------------------------------------- |
| `${date}`           | Date the letter is issued                    | Tuesday, October 6, 2026              |
| `${name}`           | Staff full name                              | Chinedu Okoro                         |
| `${name_caps}`      | Staff full name in capitals                  | CHINEDU OKORO                         |
| `${address}`        | Staff address (one line)                     | 12 Test Street, Garki, Abuja, Nigeria |
| `${position}`       | Job title/position                           | Senior Lecturer                       |
| `${department}`     | Department name                              | Mass Communication                    |
| `${start_date}`     | Contract start date                          | 6th October, 2026                     |
| `${end_date}`       | Contract end date                            | 6th October, 2028                     |
| `${duration}`       | Contract length, worked out from the dates   | Two (2) Year(s)                       |
| `${salary}`         | Salary as entered (a plain number becomes N150,000 monthly) | ₦150,000 monthly |
| `${net_salary}`     | Net salary after tax (optional - the whole line is left out if empty) | ₦94,460 |
| `${email}`          | Staff email address                          | name@example.com                      |

### How to Use Placeholders

1. Open your contract template in Microsoft Word
2. Place placeholders where you want dynamic content to appear
3. Use the exact format: `${placeholder_name}`
4. Example: "This contract is issued to **${name}** for the position of **${position}** in the **${department}**."
5. Save the document as .docx format

### Example Template Text

```
EMPLOYMENT CONTRACT

This Employment Contract is entered into on ${date} between [Company Name]
and ${name} (hereinafter referred to as "the Employee").

1. POSITION AND DUTIES
The Employee is appointed to the position of ${position} in the ${department}.

2. COMPENSATION
The Employee shall receive a salary of ${salary}.

3. CONTRACT PERIOD
This contract shall commence on ${start_date} and terminate on ${end_date}.

4. CONTACT INFORMATION
Email: ${email}

...
```

## Adding New Templates

1. Create your contract document in Microsoft Word
2. Add the placeholders listed above where needed
3. Save the file as .docx format
4. Place it in this folder
5. Update the `issue-contract.php` file to include the new template in the dropdown

## Notes

- The system supports multiple variations of placeholders (lowercase, Title Case, UPPERCASE)
- Make sure to use the exact placeholder syntax with `${}` brackets
- Test your template after creation to ensure all placeholders are being replaced correctly
