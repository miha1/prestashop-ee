#!/usr/bin/env bash
git remote rm origin
git remote add origin https://miha1:${GITHUB_TOKEN}@github.com/miha1/$DEPLOYMENT_PROJECT.git
git symbolic-ref HEAD refs/heads/master

npm i -g npm
npm install --only=dev
sh .bin/generate-release-package.sh
npx release-it --ci -eV -V
