# TMMS-24 Block for Moodle v2.0.0

A comprehensive Moodle block plugin that implements the Trait Meta-Mood Scale (TMMS-24) psychological assessment tool with complete administrative dashboard.

## Description

The TMMS-24 is a validated psychological test that measures emotional intelligence through three key dimensions:
- **Perception**: Ability to perceive and identify emotions
- **Comprehension**: Ability to understand emotions and their causes
- **Regulation**: Ability to regulate and manage emotions effectively

This block provides a complete implementation for Moodle courses with advanced administrative capabilities, detailed analytics, and role-based access control.

## Features

### For Students
- Take the TMMS-24 test directly within Moodle
- View personal results in the block
- Detailed results page with interpretations
- Multilingual support (English/Spanish)

### For Teachers/Administrators
- View all student results in a dashboard
- Access statistics for the entire course
- Export all results to CSV format
- Cannot take the test themselves (results viewing only)

## Installation

1. Download the plugin files
2. Extract to `/path/to/moodle/blocks/tmms_24/`
3. Visit your Moodle site as an administrator
4. Complete the installation process
5. Add the block to desired courses

## Requirements

- Moodle 4.1 or higher
- PHP 7.4 or higher

## Capabilities

- `block/tmms_24:view` - View and take the test (granted to students)
- `block/tmms_24:viewallresults` - View all results and statistics (granted to teachers/managers)

## Database Schema

The plugin creates a table `tmms_24` with the following structure:
- User identification and course context
- 24 individual item responses (1-5 scale)
- Demographics (age, gender)
- Timestamp

## Multilingual Support

Currently supports:
- English (en)
- Spanish (es)

All test questions and interface elements are fully translated.

## Scoring

The test uses gender-specific scoring ranges for accurate interpretation:
- **Perception**: 8 items (1-8)
- **Comprehension**: 8 items (9-16) 
- **Regulation**: 8 items (17-24)

Each dimension provides interpretation as:
- Needs Improvement
- Adequate
- Excellent (Comprehension/Regulation only)

## Version History

### v1.0.0 (2025-09-23)
- Initial release
- Complete TMMS-24 implementation
- Role-based access control
- Multilingual support (EN/ES)
- Export functionality
- Statistics dashboard

## License

This plugin is licensed under the GNU GPL v3 or later.

## Support

For issues and questions, please use the GitHub issue tracker.

## Author

Developed for ISCOUTB - Universidad Tecnológica de Bolívar