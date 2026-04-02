# 🧭 UX Writing

## ✨ Objective

This UX Writing best practices guide is designed for designers, front-end developers, translators, and product managers working on Basil, our strategic intelligence (OSINT) application.

It aims to ensure a consistent, clear, and accessible experience by harmonizing interface copy in both French and English.

## 📚 Table of Contents

1. [General Principles](#general-principles)
2. [Conventions by UI Element Type](#conventions-by-ui-element-type)
3. [Recommended Forms Summary](#recommended-forms-summary)
4. [Special Cases](#special-cases)
5. [Internationalization & Translation](#internationalization--translation)
6. [Voice & Tone Guidelines](#voice--tone-guidelines)
7. [Accessibility Considerations](#accessibility-considerations)
8. [Error Handling & Edge Cases](#error-handling--edge-cases)

## 🧱 General Principles

### 🔹 Concision

**💡 Best practice:** Get to the point.

- ✅ **Move** instead of **Move to**
- ✅ **Create** rather than **New folder**
- ✅ **Save** not **Save changes**

### 🔹 Harmonization

**💡 Use one word per concept.**
Don't alternate between synonyms.

| Bad ⛔                  | Good ✅                          |
| ----------------------- | -------------------------------- |
| Choose / Select         | Choose                           |
| Change / Modify         | Modify                           |
| Repository / References | Choose one term based on context |
| Delete / Remove         | Delete                           |

### 🔹 Punctuation (by language)

| Language   | Rule                                                |
| ---------- | --------------------------------------------------- |
| 🇫🇷 French  | Non-breaking space before: ?!; – Language: français |
| 🇬🇧 English | No space – Language: English                        |

➡️ No ellipsis or exclamation marks in buttons or tooltips.

### 🔹 English Grammar Basics

- **Create a folder** ✅ (singular determiner)
- **Track changes** ✅ (no article for plural)
- **log in** = verb / **login** = noun
- **Process stopped correctly** ✅ (adverb at end of sentence)
- Use active voice: **Delete file** not **File will be deleted**

### 🔹 Typography

- French quotes: « term »
- English quotes: 'term'
- [FR] Accent capitals: État, À faire
- ⚠️ CSS tokenization in Figma doesn't always support accented capitals → check with design team

## 🧩 Conventions by UI Element Type

### Button

**🎯 Form:** Imperative verb

- Create, Cancel, Send, Delete, Export, Import

**Examples:**

- ✅ Create process
- ✅ Manage settings
- ✅ Download report
- ❌ Creation of process

**Why:** Enables quick reading of the action to perform.

### Checkbox

**🎯 Form:** Infinitive or gerund

- Enable tracking, Keep logs, Show details

**Examples:**

- ✅ Send data as attachment
- ✅ Set queue as durable
- ✅ Enable real-time monitoring
- ❌ Data will be sent as attachment

**Why:** Immediate clarity on the effect of activation.

### Input Field

**🎯 Form:** Noun

- Name, Description, Server, Folder, Email

**Examples:**

- ✅ Search name → Name
- ✅ Datasource description → Description
- ✅ API endpoint → Endpoint
- ❌ Enter your name

**Why:** Immediate readability of expected information type.

### Tooltip (Button)

**🎯 Form:** Infinitive with determiner

- Take a screenshot, Import a process, Export data

**Examples:**

- ✅ Screenshot → Take a screenshot
- ✅ Duplicate → Duplicate this item
- ✅ Archive → Archive selected items

**Why:** More explicit than button alone.

### Tooltip (Checkbox)

**🎯 Form:** Infinitive with complement

- ✅ Browse all files
- ✅ Enable compression for faster transfers
- ❌ All files will be browsed

➡️ Avoid future tense and passive voice.

### Tooltip (Input Field)

**🎯 Form:** Infinitive or descriptive phrase

- Enter the path, Analyze a property, Specify connection details

**Examples:**

- ✅ Analyze only one property
- ✅ Required for API authentication
- ✅ Format: YYYY-MM-DD

➡️ Describe the filling action or effect.

### Tooltip (Menu)

**🎯 Form:** Plural noun

- Folders, Execution traces, Sources, Settings

➡️ Describe menu content.

### Tooltip (Panel)

**🎯 Form:** Singular noun

- History, Monitoring, Library, Dashboard

➡️ Identify panel purpose at a glance.

### Dropdown List

**🎯 Form:** Infinitive

- Create folder, Log out, Edit query, View details

➡️ Harmonization with buttons and other action elements.

### Window/Modal

**🎯 Form:** Infinitive

- Create folder, Associate source, Configure settings

➡️ Maintains link with primary action.

### Notification

**Title:** Noun phrase
**Message:** Neutral indicative, no formal address

**Examples:**

- ✅ **Deletion successful** | The source has been removed
- ✅ **Process completed** | Results are ready for review
- ✅ **Connection established** | You can now start monitoring
- ❌ **Success!** | Your file has been successfully uploaded

➡️ No imperative tone except for critical errors.

### Placeholder

**🎯 Form:** Infinitive or imperative

**Examples:**

- ✅ Choose a server type
- ✅ Enter a description
- ✅ Select date range
- ❌ Server type

➡️ Clarity, concision, consistency.

### Panel (Toggle, Sidebar)

**🎯 Form:** Noun

- History, Chat, Monitoring, Settings

➡️ Represents a function or space, not an action.

## 📊 Recommended Forms Summary

| UI Element         | Recommended Form        | Example                   |
| ------------------ | ----------------------- | ------------------------- |
| Button             | Imperative verb         | Create, Cancel, Export    |
| Checkbox           | Infinitive/Gerund       | Enable compression        |
| Input Field        | Noun                    | Name, Folder, Email       |
| Tooltip (Button)   | Infinitive + determiner | Import a file             |
| Tooltip (Field)    | Infinitive              | Enter the path            |
| Tooltip (Menu)     | Plural noun             | Sources, Filters          |
| Notification Title | Noun phrase             | Deletion successful       |
| Notification Text  | Neutral indicative      | The source is available   |
| Placeholder        | Infinitive/Imperative   | Choose a type, Enter name |
| Window/Modal       | Infinitive              | Create folder             |
| Panel              | Noun                    | History, Monitoring       |

## ⚠️ Special Cases

### Critical Warnings/Errors

- Allow future tense + strong punctuation
- **Example:** Data will be permanently deleted!
- Use red color and warning icons

### Short Placeholders

- Can remain in "Name", "Email" format if field is very constrained
- Prioritize clarity over consistency in micro-interactions

### Technical Translations

- Add context notes in i18n tool
- Maintain technical term consistency across languages
- Create glossary for domain-specific terms

## 🌍 Internationalization & Translation {#internationalization--translation}

### Technical Guidelines

- Use semantic i18n keys, not raw text
- No concatenation in translations ("Hello " + name ❌)
- Document shared glossary of terms (e.g., watchfile, repository, etc.)
- Verify with native speaker or professional translator for sensitive content
- Respect local typographic rules (see general principles)

### Cultural Considerations

- Adapt formality levels per culture
- Consider reading patterns (left-to-right vs right-to-left)
- Respect local date/time formats
- Account for text expansion (German +30%, Chinese -20%)

## 🎯 Voice & Tone Guidelines {#voice--tone-guidelines}

### Brand Voice Characteristics

- **Professional yet approachable:** Expertise without intimidation
- **Precise:** Clear technical communication
- **Confident:** Authoritative but not arrogant
- **Helpful:** Supportive throughout user journey

### Tone Variations by Context

| Context          | Tone        | Example                                              |
| ---------------- | ----------- | ---------------------------------------------------- |
| Onboarding       | Encouraging | "Let's get you started"                              |
| Error States     | Helpful     | "We couldn't find that file. Try checking the path." |
| Success States   | Satisfied   | "Analysis complete"                                  |
| Critical Actions | Serious     | "This action cannot be undone"                       |

### Words to Avoid

- **Jargon without explanation:** Use technical terms but provide context
- **Absolute statements:** "Never", "Always" (prefer "typically", "usually")
- **Negative framing:** "Don't do this" → "Try this instead"
- **Anthropomorphism:** "The system thinks" → "The system indicates"

## ♿ Accessibility Considerations

### Screen Reader Compatibility

- Use descriptive link text: "Download report" not "Click here"
- Provide alt text for informational icons
- Use proper heading hierarchy (H1, H2, H3)
- Include skip links for navigation

### Language Clarity

- Use simple, direct language
- Avoid idioms and colloquialisms
- Define technical terms on first use
- Use consistent terminology throughout

### Visual Accessibility

- Ensure sufficient color contrast
- Don't rely solely on color for meaning
- Use clear, readable fonts
- Provide text alternatives for visual information

## 🚨 Error Handling & Edge Cases {#error-handling--edge-cases}

### Error Message Structure

**Format:** [What happened] + [Why] + [What to do]

**Examples:**

- ✅ "Upload failed. File size exceeds 10MB limit. Try compressing your file."
- ✅ "Connection timeout. Server may be busy. Please try again in a moment."
- ❌ "Error 404" (too technical, no solution)

### Empty States

- Explain what this space is for
- Provide clear next steps
- Use encouraging tone

**Examples:**

- ✅ "No data sources yet. Connect your first source to start monitoring."
- ✅ "Search results will appear here. Try refining your filters."

### Loading States

- Use specific, helpful messages
- Avoid generic "Loading..."
- Provide progress indicators when possible

**Examples:**

- ✅ "Analyzing data... This may take a few minutes."
- ✅ "Connecting to server..."
- ✅ "Processing 3 of 10 files..."

## 📝 Forms & Input Guidelines

### Progressive Disclosure

- Show only essential fields initially
- Use "Show advanced options" for complex configurations
- Group related fields logically

### Validation Messages

- **Real-time:** Show as user types for complex requirements
- **On submit:** For complete validation
- **Inline:** Next to the problematic field

**Examples:**

- ✅ "Password must contain at least 8 characters"
- ✅ "This email address is already registered"
- ❌ "Invalid input" (too vague)

### Required Fields

- Use clear visual indicators (asterisks, color)
- Explain requirement levels upfront
- Consider making optional fields truly optional
