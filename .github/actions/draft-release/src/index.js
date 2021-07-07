const fs = require('fs');
const path = require('path');
const core = require('@actions/core');
const github = require('@actions/github');
const { makeRelease } = require('./utils');
const ReleaseChangelog = require('../../../../bin/release-changelog/changelog');

async function main() {
	try {
		const milestone = core.getInput('milestone', { required: true });

		// Do a sanity check on the milestone name.
		if (!/^v[0-9]+\.[0-9]+(\.[0-9]+)?$/.test(milestone)) {
			throw new Error('Milestone name is invalid.');
		}

		// Get tag name from plugin main PHP file.
		let tagName = '';
		const pluginFile = fs.readFileSync(path.resolve(process.cwd(), 'amp.php')).toString();
		const matches = /\*\s+Version:\s+(\d+(\.\d+)+-\w+)/.exec(pluginFile);
		if (matches && matches[1]) {
			[, tagName] = matches;
		}

		// Get target branch.
		const targetBranch = github.context.ref.replace('refs/heads/', '');

		core.info(`Tag: ${tagName}`);
		core.info(`Milestone: ${milestone}`);
		core.info(`Target branch: ${targetBranch}`);

		// Generate release changelog.
		const repo = process.env.GITHUB_REPOSITORY;
		const token = process.env.GITHUB_TOKEN;
		const releaseBody = await new ReleaseChangelog(repo, milestone, token).generate();

		// Make GitHub release.
		const {
			data: { html_url: htmlUrl, upload_url: assetUploadUrl }
		} = await makeRelease(tagName, releaseBody, targetBranch);

		core.info(`Release draft URL: ${htmlUrl}`);
		core.setOutput('asset_upload_url', assetUploadUrl);
	} catch (error) {
		core.setFailed(error.stack);
	}
}

main().catch(core.error);
