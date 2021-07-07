const { GitHub } = require('@actions/github');

// Use only one instance of the GitHub client (Ocktokit).
if (!global.github) {
	global.github = new GitHub(process.env.GITHUB_TOKEN);
}

export default global.github;
