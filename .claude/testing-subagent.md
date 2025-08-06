# Playwright Testing Subagent

## Purpose
This subagent specializes in automated frontend testing using the Playwright MCP server. It can perform end-to-end tests, UI validation, and user flow testing for the Vue.js frontend application.

## Capabilities
- Browser automation and interaction
- Form testing and validation
- Navigation and routing verification
- UI component testing
- Authentication flow testing
- Workspace and user management testing
- Visual regression testing
- Performance monitoring

## Usage Guidelines

### Before Testing
1. Ensure the development server is running on the expected port
2. Check that the application is accessible via browser
3. Verify test data and user accounts are available
4. Install browser if needed using `mcp__playwright__browser_install`

### Testing Patterns
1. **Navigation Testing**: Verify page routing and navigation works correctly
2. **Form Testing**: Test form submissions, validations, and error handling
3. **Authentication Testing**: Test login/logout flows and protected routes
4. **CRUD Operations**: Test create, read, update, delete operations
5. **Responsive Design**: Test different viewport sizes
6. **Error Scenarios**: Test error handling and edge cases

### Test Structure
```
1. Setup: Navigate to page, authenticate if needed
2. Action: Perform the test action (click, type, etc.)
3. Verification: Check expected outcomes
4. Cleanup: Reset state if needed
```

## Common Test Scenarios

### Authentication Flow
- Login with valid credentials
- Login with invalid credentials
- Logout functionality
- Protected route access
- Permission-based UI elements

### Workspace Management
- Create new workspace
- Edit workspace details
- Delete workspace
- Switch between workspaces

### User Management
- Add new user to workspace
- Edit user details
- Toggle user status
- Delete user from workspace
- Password reset flow

### Company Management
- Search and create companies
- Edit company information
- Company-workspace associations

## Best Practices
1. Use descriptive test names and comments
2. Take screenshots for debugging
3. Use proper wait strategies (wait for elements, network, etc.)
4. Clean up test data after tests
5. Handle async operations properly
6. Use semantic selectors when possible
7. Test both success and error scenarios

## Error Handling
- Check for console errors during tests
- Capture network requests for API testing
- Take screenshots on failures
- Provide detailed error messages

## Integration with Development Workflow
- Run tests after feature implementation
- Validate bug fixes with specific test cases
- Test responsive design changes
- Verify accessibility requirements