#!/bin/bash
#
# Quick test runner script
# Usage: ./run-tests.sh [options]
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Notion Page Viewer - Test Runner ===${NC}\n"

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}Vendor directory not found. Installing dependencies...${NC}"
    composer install
    echo ""
fi

# Default: run all tests
TEST_SUITE=""
COVERAGE=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --unit)
            TEST_SUITE="Unit"
            shift
            ;;
        --integration)
            TEST_SUITE="Integration"
            shift
            ;;
        --functional)
            TEST_SUITE="Functional"
            shift
            ;;
        --coverage)
            COVERAGE="--coverage-html coverage/"
            shift
            ;;
        --help)
            echo "Usage: ./run-tests.sh [options]"
            echo ""
            echo "Options:"
            echo "  --unit          Run only unit tests"
            echo "  --integration   Run only integration tests"
            echo "  --functional    Run only functional tests"
            echo "  --coverage      Generate HTML coverage report"
            echo "  --help          Show this help message"
            echo ""
            echo "Examples:"
            echo "  ./run-tests.sh                  # Run all tests"
            echo "  ./run-tests.sh --unit           # Run only unit tests"
            echo "  ./run-tests.sh --coverage       # Run all tests with coverage"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Build PHPUnit command
CMD="./vendor/bin/phpunit"

if [ -n "$TEST_SUITE" ]; then
    CMD="$CMD --testsuite $TEST_SUITE"
    echo -e "${YELLOW}Running $TEST_SUITE tests...${NC}\n"
else
    echo -e "${YELLOW}Running all tests...${NC}\n"
fi

if [ -n "$COVERAGE" ]; then
    CMD="$CMD $COVERAGE"
    echo -e "${YELLOW}Generating coverage report...${NC}\n"
fi

# Run tests
eval $CMD
TEST_RESULT=$?

echo ""

if [ $TEST_RESULT -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
else
    echo -e "${RED}✗ Some tests failed!${NC}"
    exit 1
fi

if [ -n "$COVERAGE" ]; then
    echo -e "${GREEN}Coverage report generated in coverage/index.html${NC}"
fi

echo ""
