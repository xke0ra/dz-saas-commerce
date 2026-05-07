#!/usr/bin/env bash

set -e

cd ~/projects/dz-saas-commerce

echo "Checking status..."
git status --short

if [ -z "$(git status --short)" ]; then
  echo "No changes to commit."
  exit 0
fi

echo ""
echo "Adding changes..."
git add .

echo ""
echo "Creating commit..."
git commit -m "${1:-Update project}"

echo ""
echo "Pushing to GitHub..."
git push

echo ""
echo "Done."
