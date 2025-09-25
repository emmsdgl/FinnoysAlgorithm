# Overview

This is a PHP web application for Fin-noys Oy, a cleaning agency management system. The application is designed to manage employees, tasks, and operations for a cleaning service company. It uses a traditional LAMP stack architecture with PHP for server-side logic, MySQL for data persistence, and PDO for database interactions. The system is planned for development across 4 phases, starting with an admin module and dashboard for core employee and task management functionality.

# User Preferences

Preferred communication style: Simple, everyday language.

# System Architecture

## Backend Architecture
- **Language**: PHP with object-oriented programming principles
- **Database Layer**: MySQL with PDO (PHP Data Objects) for secure database interactions
- **Architecture Pattern**: MVC (Model-View-Controller) pattern for separation of concerns
- **Session Management**: PHP native sessions for user authentication and state management

## Database Design
- **Primary Storage**: MySQL relational database
- **Key Entities**: 
  - Employees table with comprehensive employment data including skills (JSON), hourly rates, trial periods, and collective agreement information
  - Tasks table with skill requirements (JSON), priority levels, and scheduling capabilities
- **Data Types**: Strategic use of JSON fields for flexible skill and requirement storage
- **Constraints**: Proper primary keys, auto-increment fields, and enum types for data integrity

## Frontend Architecture
- **Presentation Layer**: Server-side rendered HTML with PHP templating
- **Styling**: CSS for responsive design and user interface
- **Client-side Logic**: Minimal JavaScript for enhanced user interactions
- **Admin Dashboard**: Centralized interface for employee and task management

## Security Considerations
- **Database Security**: PDO prepared statements to prevent SQL injection attacks
- **Input Validation**: Server-side validation and sanitization of user inputs
- **Authentication**: Session-based authentication system for admin access control

## Development Approach
- **Phased Development**: Modular development across 4 phases starting with core admin functionality
- **Code Organization**: Structured file organization with separate directories for models, views, controllers, and configuration
- **Database Schema**: Comprehensive employee management with support for Finnish employment regulations and collective agreements

# External Dependencies

## Database System
- **MySQL**: Primary relational database management system
- **PDO Extension**: PHP Data Objects for database abstraction and security

## Server Requirements
- **PHP**: Server-side scripting language (version compatibility for JSON field support)
- **Apache/Nginx**: Web server for hosting the application
- **MySQL Server**: Database server for data persistence

## Development Tools
- **LAMP/WAMP Stack**: Local development environment
- **PHP Extensions**: PDO_MySQL extension for database connectivity

## Business Context Dependencies
- **Finnish Employment Law**: Integration with Property Service Sector Collective Agreement
- **Insurance Providers**: References to Ilmarinen, TyEL, and other Finnish insurance systems
- **Regulatory Compliance**: Support for Finnish employment regulations including trial periods and working hour limitations