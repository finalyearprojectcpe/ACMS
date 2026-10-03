#!/bin/zsh

set -xe

# bunx vite build --mode development
bunx vite build && bunx vite build --ssr

git add .
git commit -m "$1"
git push
git push project master:main