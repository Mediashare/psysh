#!/bin/bash

cd /Users/fullname/Desktop/psysh

echo "Testing profile command manually:"
echo 'echo "Hello World!";' | php bin/psysh -c 'profile echo "Hello World!";'
