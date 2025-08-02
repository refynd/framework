# GitHub Linguist Submission Guide for Prism

This guide will help you submit Prism to GitHub Linguist for official language recognition.

## 🎯 What We're Submitting

**Prism** - An elegant templating language for the Refynd PHP framework that combines:
- Blade-style directives (`@extends`, `@section`, `@include`)
- Twig-style control structures (`{% if %}`, `{% foreach %}`)
- Clean output syntax (`{{ $var }}`, `{{{ $raw }}}`)
- Inline PHP blocks (`{% php %}`)

## 📋 Submission Checklist

### ✅ Required Files
1. **Language Definition** (`linguist-definition.yml`) - ✓ Created
2. **Sample Files** - ✓ Created (3 comprehensive examples)
3. **TextMate Grammar** (`prism.tmLanguage.json`) - ✓ Created
4. **Documentation** - This guide

### 🎨 Language Details
- **Name**: Prism
- **Type**: markup (templating language)
- **Color**: `#7c4dff` (deep purple - represents light refraction)
- **Extensions**: `.prism`
- **Language ID**: 584 (unique identifier)

## 🚀 Submission Steps

### 1. Fork GitHub Linguist
```bash
# Fork https://github.com/github/linguist
git clone https://github.com/YOUR-USERNAME/linguist.git
cd linguist
```

### 2. Add Language Definition
Edit `lib/linguist/languages.yml` and add:

```yaml
Prism:
  type: markup
  color: "#f770f9ff"
  extensions:
  - ".prism"
  tm_scope: text.html.prism
  ace_mode: html
  language_id: 584
```

### 3. Add Sample Files
Create directory `samples/Prism/` and add the sample files:
- `homepage.prism`
- `layout.prism` 
- `user-profile.prism`

### 4. Add Grammar File (Optional)
Place `prism.tmLanguage.json` in `grammars/` directory for syntax highlighting.

### 5. Update Documentation
Add entry to `CONTRIBUTING.md` if needed.

### 6. Run Tests
```bash
# Test language detection
bundle exec ruby test/test_linguist.rb

# Test sample classification  
bundle exec ruby test/test_samples.rb
```

### 7. Submit Pull Request
- Title: "Add Prism templating language"
- Description: Include framework context and syntax examples
- Reference: Link to Refynd framework repository

## 📝 PR Description Template

```markdown
## Add Prism Templating Language

This PR adds support for **Prism**, the templating language used by the Refynd PHP framework.

### Language Details
- **Repository**: https://github.com/refynd/framework
- **Type**: Markup/Template language
- **Extensions**: `.prism`
- **Framework**: Refynd (Modern PHP framework)

### Syntax Highlights
Prism combines the best of Blade and Twig:

**Directives** (Blade-style):
```prism
@extends('layout')
@section('content')
@include('partial')
```

**Control Structures** (Twig-style):
```prism
{% if $condition %}
{% foreach $items as $item %}
{% endforeach %}
{% endif %}
```

**Output**:
```prism
{{ $escaped }}      {{-- HTML escaped --}}
{{{ $raw }}}        {{-- Raw output --}}
{{-- Comment --}}   {{-- Template comment --}}
```

### Sample Files
- `homepage.prism` - Complete page example
- `layout.prism` - Layout inheritance
- `user-profile.prism` - Complex template with loops/conditionals

### Framework Context
Prism is the templating engine for Refynd, a modern PHP framework focused on developer experience and clean architecture. The language is actively used in production applications.

**Documentation**: https://github.com/refynd/framework/docs
**Framework Version**: 1.2.0+
```

## 🎯 Success Criteria

For successful acceptance:
- [ ] Unique syntax patterns distinguishable from other template languages
- [ ] Active usage in real projects (Refynd framework)
- [ ] Clear file extension (`.prism`)
- [ ] Comprehensive sample files showing language features
- [ ] Proper language classification (markup/template)

## 🔄 Timeline
- **Submission**: Immediate (after PR creation)
- **Review**: 2-4 weeks typically
- **Acceptance**: 4-8 weeks for new languages
- **Deployment**: Next Linguist release

## 📞 Support
If you need help with the submission:
- GitHub Linguist Issues: https://github.com/github/linguist/issues
- Linguist Contributing Guide: https://github.com/github/linguist/blob/master/CONTRIBUTING.md

## 🎨 Color Justification
**#f770f9ff** (Bright Magenta):
- Represents light refraction and prism spectrum
- Highly distinguishable from existing template languages
- Vibrant and modern appearance matching Refynd's innovative spirit
- Excellent visibility and contrast

---

**Ready to make Prism officially recognized on GitHub!** 🚀
