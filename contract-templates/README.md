# Contract Templates Guide

## Overview

This folder contains the DOCX contract templates used for generating employment contracts for different locations.

## Available Templates

1. **Gombe_Template.docx** - Contract template for Gombe location
2. **Abuja_Template.docx** - Contract template for Abuja location

## Template Placeholders

To enable automatic data replacement, use the following placeholders in your DOCX templates. These placeholders will be replaced with actual values when generating contracts.

### Available Placeholders

| Placeholder                                           | Description         | Example Value        |
| ----------------------------------------------------- | ------------------- | -------------------- |
| `${name}` or `${Name}` or `${NAME}`                   | Staff full name     | John Doe             |
| `${position}` or `${Position}` or `${POSITION}`       | Job title/position  | Senior Lecturer      |
| `${department}` or `${Department}` or `${DEPARTMENT}` | Department name     | Chemistry Department |
| `${salary}` or `${Salary}`                            | Salary amount       | N500,000 per month   |
| `${date}` or `${Date}`                                | Contract issue date | 25th February, 2026  |
| `${start_date}` or `${StartDate}`                     | Contract start date | 1st March, 2026      |
| `${end_date}` or `${EndDate}`                         | Contract end date   | 28th February, 2027  |
| `${email}` or `${Email}`                              | Staff email address | john.doe@example.com |

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
