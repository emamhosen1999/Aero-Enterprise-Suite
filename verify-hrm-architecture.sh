#!/bin/bash

# HRM Architecture Verification Script
# This script performs automated verification of HRM architecture compliance

echo "========================================"
echo "HRM Architecture Verification Script"
echo "========================================"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

FAILED=0
WARNINGS=0
PASSED=0

# Function to check for violations
check_violation() {
    local description=$1
    local pattern=$2
    local path=$3
    local count=0
    
    echo -n "Checking: $description... "
    
    if [ -d "$path" ]; then
        count=$(grep -r "$pattern" "$path" --include="*.php" 2>/dev/null | wc -l)
    else
        echo -e "${YELLOW}[SKIP - Path not found]${NC}"
        return
    fi
    
    if [ $count -gt 0 ]; then
        echo -e "${RED}[FAIL] Found $count violations${NC}"
        ((FAILED++))
        return 1
    else
        echo -e "${GREEN}[PASS]${NC}"
        ((PASSED++))
        return 0
    fi
}

# Function to check for required file
check_required_file() {
    local description=$1
    local filepath=$2
    
    echo -n "Checking: $description... "
    
    if [ -f "$filepath" ]; then
        echo -e "${GREEN}[PASS]${NC}"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}[FAIL] File not found${NC}"
        ((FAILED++))
        return 1
    fi
}

echo "1. EMPLOYEE MODEL EXISTENCE"
echo "----------------------------"
check_required_file "Employee model exists" "app/Models/HRM/Employee.php"
# Check for migration with wildcard
if ls database/migrations/*create_employees_table.php 1> /dev/null 2>&1; then
    echo -n "Checking: Employee migration exists... "
    echo -e "${GREEN}[PASS]${NC}"
    ((PASSED++))
else
    echo -n "Checking: Employee migration exists... "
    echo -e "${RED}[FAIL] File not found${NC}"
    ((FAILED++))
fi
echo ""

echo "2. DIRECT USER REFERENCES IN HRM"
echo "---------------------------------"
check_violation "No User imports in HR controllers" "use App\\\\Models\\\\User" "app/Http/Controllers/HR"
check_violation "No User imports in HRM models" "use App\\\\Models\\\\User" "app/Models/HRM"
check_violation "No User:: static calls in HR controllers" "User::" "app/Http/Controllers/HR"
echo ""

echo "3. HARDCODED ROLE CHECKS"
echo "------------------------"
check_violation "No ->role( calls in HR controllers" "->role(" "app/Http/Controllers/HR"
check_violation "No hasRole calls in HR controllers" "hasRole(" "app/Http/Controllers/HR"
check_violation "No role string 'Employee' in controllers" "'Employee'" "app/Http/Controllers/HR"
check_violation "No role string 'HR Manager' in controllers" "'HR Manager'" "app/Http/Controllers/HR"
echo ""

echo "4. ONBOARDING ENFORCEMENT"
echo "-------------------------"
check_required_file "Onboarding middleware exists" "app/Http/Middleware/EnsureEmployeeOnboarded.php"
echo ""

echo "5. MODULE PERMISSION SERVICE USAGE"
echo "-----------------------------------"
# This checks if ModulePermissionService is imported in HR controllers
count=$(grep -r "use App\\\\Services\\\\Module\\\\ModulePermissionService" "app/Http/Controllers/HR" --include="*.php" 2>/dev/null | wc -l)
echo -n "Checking: ModulePermissionService usage in HR controllers... "
if [ $count -gt 5 ]; then
    echo -e "${GREEN}[PASS] Used in $count files${NC}"
    ((PASSED++))
elif [ $count -gt 0 ]; then
    echo -e "${YELLOW}[WARNING] Only used in $count files, expected more${NC}"
    ((WARNINGS++))
else
    echo -e "${RED}[FAIL] Not used in HR controllers${NC}"
    ((FAILED++))
fi
echo ""

echo "6. DATABASE CONSTRAINTS"
echo "-----------------------"
# Check if migrations constrain to employees table
count=$(grep -r "constrained('employees')" "database/migrations" --include="*onboarding*.php" 2>/dev/null | wc -l)
echo -n "Checking: Foreign keys to employees table in onboarding... "
if [ $count -gt 0 ]; then
    echo -e "${GREEN}[PASS]${NC}"
    ((PASSED++))
else
    echo -e "${RED}[FAIL] Foreign keys still point to users table${NC}"
    ((FAILED++))
fi
echo ""

echo "7. CROSS-PACKAGE BOUNDARIES"
echo "---------------------------"
check_required_file "EmployeeContract exists" "app/Contracts/EmployeeContract.php"
check_required_file "EmployeeDTO exists" "app/DTOs/EmployeeDTO.php"
echo ""

echo "8. DOMAIN EVENTS"
echo "----------------"
count=$(find app/Events -name "*Employee*.php" 2>/dev/null | wc -l)
echo -n "Checking: Employee domain events exist... "
if [ $count -gt 0 ]; then
    echo -e "${GREEN}[PASS] Found $count event(s)${NC}"
    ((PASSED++))
else
    echo -e "${YELLOW}[WARNING] No employee domain events found${NC}"
    ((WARNINGS++))
fi
echo ""

echo "9. ARCHITECTURE TESTS"
echo "---------------------"
check_required_file "Architecture test exists" "tests/Unit/Architecture/HrmEmployeeArchitectureTest.php"
echo ""

# Summary
echo "========================================"
echo "VERIFICATION SUMMARY"
echo "========================================"
echo -e "${GREEN}Passed:   $PASSED${NC}"
echo -e "${YELLOW}Warnings: $WARNINGS${NC}"
echo -e "${RED}Failed:   $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ Architecture verification PASSED${NC}"
    exit 0
elif [ $FAILED -lt 5 ]; then
    echo -e "${YELLOW}⚠ Architecture verification PARTIALLY PASSED with issues${NC}"
    exit 1
else
    echo -e "${RED}✗ Architecture verification FAILED${NC}"
    exit 1
fi
