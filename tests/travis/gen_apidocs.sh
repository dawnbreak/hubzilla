#!/usr/bin/env bash

#
# Copyright (c) 2016 Hubzilla
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in
# all copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.
#

# Exit if anything fails
set -e

# Only create and deploy API documentation once, on first build job.
# Waiting for upcoming 'Build Stages' Q1/Q2 2017 to make this cleaner.
# https://github.com/travis-ci/travis-ci/issues/929
if [[ "$TRAVIS_JOB_NUMBER" != "${TRAVIS_BUILD_NUMBER}.1" ]]; then
	echo "Not the first build job. Creating API documentation only once is enough."
	echo "We are finished ..."
	exit
fi

# Get newer Doxygen
#echo "Doxygen version 1.7 is too old for us :("
#doxygen --version

# Travis CI has an old Doxygen 1.7 build, so we fetch a recent static binary
export DOXY_BINPATH=$HOME/doxygen/doxygen-$DOXY_VER/bin
if [ ! -e "$DOXY_BINPATH/doxygen" ]; then
	echo "Installing newer Doxygen $DOXY_VER ..."
	mkdir -p $HOME/doxygen && cd $HOME/doxygen
	wget -O - http://ftp.stack.nl/pub/users/dimitri/doxygen-$DOXY_VER.linux.bin.tar.gz | tar xz
	export PATH=$PATH:$DOXY_BINPATH
fi
echo "Doxygen version"
doxygen --version

echo "Generating Doxygen API documentation ..."
cd $TRAVIS_BUILD_DIR
mkdir -p ./doc/html
# Redirect stderr and stdout to log file and console to be able to review documentation errors
doxygen $DOXYFILE 2>&1 | tee ./doc/html/doxygen.log


# There is no sane way yet, to prevent missuse of the push tokens in our workflow.
# We would need a way to limit a token to only push to gh-pages or a way to prevent
# manipulations to travis scripts which is not possible because we want it to run
# for pull requests.
# There are protected branches in GitHub, but they do not work for forced pushes.
exit

# Only continue for master branch pushes
if [[ "$TRAVIS_EVENT_TYPE" != "push" ]] || [[ "$TRAVIS_BRANCH" != "master" ]]; then
	echo "Conditions not met to build API documentation."
	echo "We are finished ..."
#	exit
fi

# Check if GitHub token is configured in Travis to be able to push to the repo
if [ -z "$GHP_TOKEN" ]; then
	echo "No GitHub token configured in Travis, can not deploy to gh-pages ..."
	echo "Add Environment Variable 'GHP_TOKEN' in Travis CI project settings"
	echo "with a 'Personal access token' from GitHub with 'repo:public_repo'."
	exit
fi

# Upload the API documentation to the gh-pages branch of the GitHub repository.
# Only upload if Doxygen successfully created the documentation.
if [ -d "doc/html" ] && [ -f "doc/html/index.html" ]; then
	echo "Uploading API documentation to the gh-pages branch ..."

	# Add the new API documentation as a Git commit
	cd ./doc/html

	# Create and configure a new git repo for committing to gh-pages
	git init
	# Add a fake Travis CI user
	git config user.name "Travis CI"
	git config user.email "travis@travis-ci.org"
	# Add the generated API documentation
	git add --all
	git commit -q -m "API documentation generated by Travis build: ${TRAVIS_BUILD_NUMBER}" -m "From commit: ${TRAVIS_COMMIT}"

	# No need for a history, force push to the remote gh-pages branch
	git push -f "https://${GHP_TOKEN}@${GHP_REPO_REF}" master:gh-pages > /dev/null 2>&1
else
	echo "No API documentation files have been found" >&2
	exit 1
fi
