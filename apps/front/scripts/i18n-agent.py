#!/usr/bin/env python3
"""
i18n Translation Agent for Vue 3 + TypeScript

This agent scans Vue files in pages/ and components/ directories to:
1. Detect untranslated text in templates
2. Verify all i18n keys exist in both en-US.ts and fr-FR.ts
3. Auto-translate missing translations (English ↔ French)
4. Update Vue files to use $t() syntax
5. Commit changes with gitmoji format

Author: Claude AI
"""

import os
import re
import json
import subprocess
from pathlib import Path
from typing import Dict, List, Set, Tuple, Optional
from dataclasses import dataclass, field
from anthropic import Anthropic

# Configuration
ROOT_DIR = Path(__file__).parent.parent
PAGES_DIR = ROOT_DIR / "src" / "pages"
COMPONENTS_DIR = ROOT_DIR / "src" / "components"
LOCALES_DIR = ROOT_DIR / "src" / "i18n" / "locales"
EN_LOCALE_FILE = LOCALES_DIR / "en-US.ts"
FR_LOCALE_FILE = LOCALES_DIR / "fr-FR.ts"

# Patterns
I18N_PATTERN = re.compile(r'\$t\([\'"]([^\'")]+)[\'"](?:,\s*[\'"][^\'"]*[\'"])?\)')
TEMPLATE_TEXT_PATTERN = re.compile(
    r"<(?:h1|h2|h3|h4|h5|h6|p|span|div|a|button|label|th|td|li|option)[^>]*>([^<{}]+)</(?:h1|h2|h3|h4|h5|h6|p|span|div|a|button|label|th|td|li|option)>"
)
ATTRIBUTE_TEXT_PATTERN = re.compile(
    r'(?:placeholder|title|label|alt|aria-label)=["\']([^"\']+)["\']'
)


@dataclass
class TranslationStats:
    """Statistics about translation changes"""

    files_scanned: int = 0
    files_modified: int = 0
    keys_added: int = 0
    keys_verified: int = 0
    missing_translations: List[Tuple[str, str]] = field(default_factory=list)
    new_translations: Dict[str, Dict[str, str]] = field(default_factory=dict)


class LocaleManager:
    """Manages locale files and translations"""

    def __init__(self, en_file: Path, fr_file: Path):
        self.en_file = en_file
        self.fr_file = fr_file
        self.en_translations: Dict[str, str] = {}
        self.fr_translations: Dict[str, str] = {}
        self.load_translations()

    def load_translations(self):
        """Load translations from TypeScript locale files"""
        self.en_translations = self._parse_locale_file(self.en_file)
        self.fr_translations = self._parse_locale_file(self.fr_file)

    def _parse_locale_file(self, file_path: Path) -> Dict[str, str]:
        """Parse TypeScript export default object into flat key-value dict"""
        content = file_path.read_text(encoding="utf-8")

        # Remove export default and trailing curly brace
        content = re.sub(r"^export\s+default\s+\{", "{", content, flags=re.MULTILINE)

        translations = {}
        self._extract_keys(content, [], translations)
        return translations

    def _extract_keys(self, content: str, prefix: List[str], result: Dict[str, str]):
        """Recursively extract nested keys from JS object"""
        # Match key-value pairs (both string values and nested objects)
        key_value_pattern = re.compile(
            r"(['\"]?)(\w+)(['\"]?)\s*:\s*(?:(['\"])((?:\\.|[^'\"\\])*)\4|(\{))",
            re.MULTILINE | re.DOTALL,
        )

        pos = 0
        brace_depth = 0

        for match in key_value_pattern.finditer(content):
            key = match.group(2)
            string_value = match.group(5)
            is_object = match.group(6)

            full_key = ".".join(prefix + [key])

            if string_value is not None:
                # It's a string value
                result[full_key] = string_value
            elif is_object:
                # It's a nested object - find its content
                obj_start = match.end()
                brace_count = 1
                obj_end = obj_start

                for i in range(obj_start, len(content)):
                    if content[i] == "{":
                        brace_count += 1
                    elif content[i] == "}":
                        brace_count -= 1
                        if brace_count == 0:
                            obj_end = i
                            break

                nested_content = content[obj_start:obj_end]
                self._extract_keys(nested_content, prefix + [key], result)

    def key_exists(self, key: str) -> Tuple[bool, bool]:
        """Check if key exists in both locales (en, fr)"""
        return (key in self.en_translations, key in self.fr_translations)

    def add_translation(self, key: str, en_text: str, fr_text: str):
        """Add translation to both locale dicts"""
        self.en_translations[key] = en_text
        self.fr_translations[key] = fr_text

    def save_translations(self):
        """Save translations back to TypeScript files"""
        self._save_locale_file(self.en_file, self.en_translations)
        self._save_locale_file(self.fr_file, self.fr_translations)

    def _save_locale_file(self, file_path: Path, translations: Dict[str, str]):
        """Convert flat dict back to nested TypeScript object"""
        nested = {}

        for key, value in sorted(translations.items()):
            parts = key.split(".")
            current = nested

            for part in parts[:-1]:
                if part not in current:
                    current[part] = {}
                current = current[part]

            current[parts[-1]] = value

        content = "export default " + self._dict_to_ts(nested, 0)
        file_path.write_text(content, encoding="utf-8")

    def _dict_to_ts(self, obj: Dict, indent_level: int) -> str:
        """Convert nested dict to TypeScript object string"""
        if not obj:
            return "{}"

        indent = "  " * indent_level
        next_indent = "  " * (indent_level + 1)
        lines = ["{"]

        for key, value in obj.items():
            if isinstance(value, dict):
                nested = self._dict_to_ts(value, indent_level + 1)
                lines.append(f"{next_indent}{key}: {nested},")
            else:
                # Escape single quotes and backslashes
                escaped_value = value.replace("\\", "\\\\").replace("'", "\\'")
                lines.append(f"{next_indent}{key}: '{escaped_value}',")

        lines.append(f"{indent}}}")
        return "\n".join(lines)


class TranslationAgent:
    """Main agent for detecting and fixing i18n issues"""

    def __init__(self, anthropic_api_key: Optional[str] = None):
        self.locale_manager = LocaleManager(EN_LOCALE_FILE, FR_LOCALE_FILE)
        self.stats = TranslationStats()

        # Initialize Anthropic client for translations
        if anthropic_api_key:
            self.anthropic = Anthropic(api_key=anthropic_api_key)
        else:
            self.anthropic = None
            print(
                "⚠️  No Anthropic API key provided. Translation will use placeholder text."
            )

    def scan_directory(self, directory: Path):
        """Recursively scan directory for Vue files"""
        for vue_file in directory.rglob("*.vue"):
            self.stats.files_scanned += 1
            print(f"📄 Scanning: {vue_file.relative_to(ROOT_DIR)}")

            modified = self.process_vue_file(vue_file)
            if modified:
                self.stats.files_modified += 1

    def process_vue_file(self, file_path: Path) -> bool:
        """Process a single Vue file for translation issues"""
        content = file_path.read_text(encoding="utf-8")
        original_content = content

        # Extract template section
        template_match = re.search(r"<template>(.*?)</template>", content, re.DOTALL)
        if not template_match:
            return False

        template = template_match.group(1)

        # Find existing i18n keys and verify they exist
        self._verify_existing_keys(template, file_path)

        # Find untranslated text and convert to i18n
        template, changes_made = self._convert_untranslated_text(template, file_path)

        if changes_made:
            # Replace template in content
            content = (
                content[: template_match.start(1)]
                + template
                + content[template_match.end(1) :]
            )
            file_path.write_text(content, encoding="utf-8")
            return True

        return False

    def _verify_existing_keys(self, template: str, file_path: Path):
        """Verify that all used i18n keys exist in both locales"""
        keys = I18N_PATTERN.findall(template)

        for key in keys:
            self.stats.keys_verified += 1
            en_exists, fr_exists = self.locale_manager.key_exists(key)

            if not en_exists or not fr_exists:
                missing_locales = []
                if not en_exists:
                    missing_locales.append("en-US")
                if not fr_exists:
                    missing_locales.append("fr-FR")

                self.stats.missing_translations.append(
                    (
                        str(file_path.relative_to(ROOT_DIR)),
                        f"Key '{key}' missing in: {', '.join(missing_locales)}",
                    )
                )

    def _convert_untranslated_text(
        self, template: str, file_path: Path
    ) -> Tuple[str, bool]:
        """Find and convert untranslated text to i18n"""
        changes_made = False

        # Find text in tags
        for match in TEMPLATE_TEXT_PATTERN.finditer(template):
            text = match.group(1).strip()

            # Skip if already using i18n or is not translatable
            if not self._is_translatable(text):
                continue

            # Generate key and add translation
            key = self._generate_key(text, file_path)
            en_text, fr_text = self._translate_text(text)

            # Add to locale files
            self.locale_manager.add_translation(key, en_text, fr_text)
            self.stats.keys_added += 1

            # Track new translation
            if key not in self.stats.new_translations:
                self.stats.new_translations[key] = {"en": en_text, "fr": fr_text}

            # Replace in template
            replacement = f"{{{{ $t('{key}') }}}}"
            template = template.replace(
                match.group(0), match.group(0).replace(match.group(1), replacement)
            )
            changes_made = True

        # Find text in attributes
        for match in ATTRIBUTE_TEXT_PATTERN.finditer(template):
            text = match.group(1).strip()

            if not self._is_translatable(text):
                continue

            key = self._generate_key(text, file_path)
            en_text, fr_text = self._translate_text(text)

            self.locale_manager.add_translation(key, en_text, fr_text)
            self.stats.keys_added += 1

            if key not in self.stats.new_translations:
                self.stats.new_translations[key] = {"en": en_text, "fr": fr_text}

            # Replace attribute value with v-bind
            attr_name = match.group(0).split("=")[0]
            template = template.replace(match.group(0), f":{attr_name}=\"$t('{key}')\"")
            changes_made = True

        return template, changes_made

    def _is_translatable(self, text: str) -> bool:
        """Check if text should be translated"""
        # Skip empty, whitespace, or very short text
        if not text or len(text.strip()) < 2:
            return False

        # Skip if already using interpolation
        if "{{" in text or "}}" in text:
            return False

        # Skip numbers
        if text.strip().replace(".", "").replace(",", "").isdigit():
            return False

        # Skip common non-translatable patterns
        skip_patterns = [
            r"^[\d\s\.\,\-\:\;]+$",  # Numbers and punctuation
            r"^[A-Z]{2,}$",  # Acronyms like "ID", "URL"
            r"^https?://",  # URLs
            r"^@",  # Mentions/decorators
            r"^\$",  # Variables
        ]

        for pattern in skip_patterns:
            if re.match(pattern, text.strip()):
                return False

        return True

    def _generate_key(self, text: str, file_path: Path) -> str:
        """Generate i18n key from text and file context"""
        # Get section from file path
        relative_path = file_path.relative_to(ROOT_DIR)
        parts = list(relative_path.parts)

        # Determine section (pages/components/etc)
        if "pages" in parts:
            section_idx = parts.index("pages")
            section_parts = parts[section_idx + 1 :]
        elif "components" in parts:
            section_idx = parts.index("components")
            section_parts = parts[section_idx + 1 :]
        else:
            section_parts = parts

        # Build base key from path
        base_parts = []
        for part in section_parts[:-1]:  # Exclude filename
            # Remove special chars from folder names
            clean_part = re.sub(r"[^\w]", "", part)
            if clean_part:
                base_parts.append(clean_part)

        # Generate key from text
        text_key = re.sub(r"[^\w\s]", "", text.lower())
        text_key = re.sub(r"\s+", "_", text_key)[:30]  # Limit length

        # Combine
        if base_parts:
            key = ".".join(base_parts) + "." + text_key
        else:
            key = text_key

        # Ensure uniqueness
        counter = 1
        original_key = key
        while key in self.locale_manager.en_translations:
            key = f"{original_key}_{counter}"
            counter += 1

        return key

    def _translate_text(self, text: str) -> Tuple[str, str]:
        """Translate text to both English and French"""
        # Assume input text is English by default
        en_text = text.strip()

        # Use Claude API if available for French translation
        if self.anthropic:
            try:
                message = self.anthropic.messages.create(
                    model="claude-3-5-sonnet-20241022",
                    max_tokens=200,
                    messages=[
                        {
                            "role": "user",
                            "content": f"Translate this English text to French. Only respond with the translation, nothing else:\n\n{en_text}",
                        }
                    ],
                )
                fr_text = message.content[0].text.strip()
            except Exception as e:
                print(f"⚠️  Translation API error: {e}")
                fr_text = f"[FR] {en_text}"
        else:
            fr_text = f"[FR] {en_text}"

        return en_text, fr_text

    def generate_report(self) -> str:
        """Generate statistics report"""
        report = [
            "\n" + "=" * 60,
            "📊 i18n Translation Agent Report",
            "=" * 60,
            f"\n📄 Files scanned: {self.stats.files_scanned}",
            f"✏️  Files modified: {self.stats.files_modified}",
            f"🔑 Keys verified: {self.stats.keys_verified}",
            f"➕ New keys added: {self.stats.keys_added}",
        ]

        if self.stats.missing_translations:
            report.append(
                f"\n⚠️  Missing translations found: {len(self.stats.missing_translations)}"
            )
            for file, issue in self.stats.missing_translations[:10]:
                report.append(f"   - {file}: {issue}")
            if len(self.stats.missing_translations) > 10:
                report.append(
                    f"   ... and {len(self.stats.missing_translations) - 10} more"
                )

        if self.stats.new_translations:
            report.append(
                f"\n🌍 New translations added: {len(self.stats.new_translations)}"
            )
            for key, translations in list(self.stats.new_translations.items())[:20]:
                report.append(f"   {key}:")
                report.append(f"      en: {translations['en']}")
                report.append(f"      fr: {translations['fr']}")
            if len(self.stats.new_translations) > 20:
                report.append(
                    f"   ... and {len(self.stats.new_translations) - 20} more"
                )

        report.append("\n" + "=" * 60 + "\n")
        return "\n".join(report)

    def commit_changes(self):
        """Commit changes with gitmoji format"""
        if self.stats.files_modified == 0 and self.stats.keys_added == 0:
            print("✅ No changes to commit")
            return

        # Stage changed files
        subprocess.run(["git", "add", str(LOCALES_DIR)], cwd=ROOT_DIR)
        subprocess.run(["git", "add", str(PAGES_DIR)], cwd=ROOT_DIR)
        subprocess.run(["git", "add", str(COMPONENTS_DIR)], cwd=ROOT_DIR)

        # Create commit message
        commit_message = f"""🌍 i18n: add {self.stats.keys_added} translations and update {self.stats.files_modified} files

- Added {self.stats.keys_added} new translation keys
- Updated {self.stats.files_modified} Vue files to use i18n
- Verified {self.stats.keys_verified} existing translation keys

"""

        # Commit
        result = subprocess.run(
            ["git", "commit", "-m", commit_message],
            cwd=ROOT_DIR,
            capture_output=True,
            text=True,
        )

        if result.returncode == 0:
            print("✅ Changes committed successfully")
        else:
            print(f"❌ Commit failed: {result.stderr}")


def main():
    """Main entry point"""
    import sys

    # Get Anthropic API key from environment or args
    api_key = os.getenv("ANTHROPIC_API_KEY")
    if len(sys.argv) > 1:
        api_key = sys.argv[1]

    print("🚀 Starting i18n Translation Agent")
    print(f"📂 Root directory: {ROOT_DIR}")
    print(f"🌍 Locales: {EN_LOCALE_FILE.name}, {FR_LOCALE_FILE.name}\n")

    agent = TranslationAgent(api_key)

    # Scan directories
    print("Scanning pages directory...")
    agent.scan_directory(PAGES_DIR)

    print("\nScanning components directory...")
    agent.scan_directory(COMPONENTS_DIR)

    # Save translations
    if agent.stats.keys_added > 0:
        print("\n💾 Saving translations to locale files...")
        agent.locale_manager.save_translations()

    # Generate report
    report = agent.generate_report()
    print(report)

    # Commit changes
    should_commit = input("Do you want to commit these changes? (y/n): ").lower()
    if should_commit == "y":
        agent.commit_changes()
    else:
        print("⏭️  Skipping commit")


if __name__ == "__main__":
    main()
