@echo off
if "%1"=="one" (
    shift
    php bin/oneweb %1 %2 %3 %4 %5 %6 %7 %8 %9
) else (
    echo Usage: run one [args]
)
