# Changelog

All notable changes to the TMMS-24 Moodle Block will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-09-23

### Added
- Initial release of TMMS-24 Moodle Block
- Complete implementation of the 24-item Trait Meta-Mood Scale psychological test
- Role-based access control:
  - Students can take the test and view their results
  - Teachers/Administrators can view all results and statistics
- Multilingual support (English and Spanish)
- Three-dimensional scoring system:
  - Perception of emotions (8 items)
  - Comprehension of emotions (8 items) 
  - Regulation of emotions (8 items)
- Gender-specific interpretation guidelines
- Statistics dashboard for teachers showing:
  - Total completed tests
  - Average scores per dimension
  - Individual student results table
- CSV export functionality for all results
- Database schema for efficient data storage
- Responsive design integrated with Moodle theme
- Comprehensive capability system for access control
- Student results display directly in block
- Detailed results page with full interpretations

### Security
- Capability-based access control preventing unauthorized access
- Input validation for all form submissions
- Secure export functionality restricted to authorized users

### Requirements
- Moodle 4.1 or higher
- PHP 7.4 or higher

### Database Schema
- Single table `tmms_24` storing:
  - User and course identification
  - Individual item responses (1-5 scale)
  - Demographics (age, gender)
  - Timestamps for completion tracking