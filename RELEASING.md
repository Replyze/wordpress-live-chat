# Releasing

The plugin is the `replyze/` folder. A release is a tag `vX.Y.Z` on `main`; `.github/workflows/release.yml` does the rest.

## Every release

1. On a branch, bump the version in all four places and describe the change:
   - `replyze/replyze.php`: the `Version:` header and `define( 'REPLYZE_VERSION', ... )`
   - `replyze/readme.txt`: `Stable tag:`, a `= X.Y.Z =` entry under `== Changelog ==`, and one under `== Upgrade Notice ==`
   - `CHANGELOG.md`: a `## [X.Y.Z] - YYYY-MM-DD` section (replace `Unreleased` with the date) and the compare link at the bottom
   - When a new WordPress version is out and the plugin works on it: `Tested up to:` in `readme.txt`
2. Open a pull request. CI must be green: `php -l` on PHP 7.4 to 8.4, PHPCS, Plugin Check, and the "Versions agree" job.
3. Merge to `main`, then tag the merge commit as Replyze and push the tag:

   ```bash
   git checkout main && git pull
   git -c user.name="Replyze" -c user.email="dev@replyze.chat" tag -a v1.2.1 -m "v1.2.1"
   git push origin v1.2.1
   ```

4. The release workflow then:
   - checks that the tag matches the header, the constant, `Stable tag`, the readme changelog and `CHANGELOG.md` (it stops otherwise);
   - builds `replyze.zip` with the `replyze/` folder at its root;
   - creates the GitHub release "Replyze for WordPress X.Y.Z" with that ZIP and the version's section of `CHANGELOG.md` as notes (if the release already exists, it only attaches the ZIP);
   - deploys to wordpress.org, only when the repository variable `WORDPRESS_ORG_DEPLOY` is `true` (below). Otherwise the run prints a notice and that job is skipped.

The README's Quickstart links to `releases/latest`, so it points to the new ZIP as soon as the release exists.

## Build the ZIP by hand

```bash
bash bin/build-zip.sh                 # dist/replyze.zip, with replyze/ at its root
bash bin/changelog-section.sh 1.2.1   # the release notes for a version
```

CI builds the same ZIP on every push; it is the `replyze-zip` artifact of the CI run.

## wordpress.org (one-time setup)

The slug `replyze` was free on 2026-11-13. Until the plugin is approved, the SVN job stays skipped.

1. Create a wordpress.org account for the company. Its username must be the one in `Contributors:` in `replyze/readme.txt` (now `replyze`); change that line if the account gets another name.
2. Submit the plugin at https://wordpress.org/plugins/developers/add/ with the `replyze.zip` of the latest release. Review takes days to weeks; answer the review emails from that account.
3. After approval, the plugin gets an SVN repository at `https://plugins.svn.wordpress.org/replyze/`. The SVN credentials go into a protected GitHub environment, never into repository secrets, so only the deploy job can read them:
   1. Settings → Environments → New environment → `wordpress-org`. Under **Deployment branches and tags**, choose **Selected branches and tags** and add the tag rule `v*`. Optionally add yourself under **Required reviewers**, so every deploy waits for your approval.
   2. In that environment, add two **environment secrets**:
      - `SVN_USERNAME`: the wordpress.org username
      - `SVN_PASSWORD`: the SVN password from https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password (a separate password for SVN only; it is not the account password and can be reset there at any time)
   3. Add the repository variable `WORDPRESS_ORG_DEPLOY` = `true` (Settings → Secrets and variables → Actions → Variables).

   `gh secret list -R Replyze/wordpress-live-chat` (repository secrets) must print nothing; `gh secret list -R Replyze/wordpress-live-chat --env wordpress-org` lists the two names.
4. The next tag deploys the `replyze/` folder to SVN `trunk` and `tags/X.Y.Z` (with `10up/action-wordpress-plugin-deploy`, pinned to the commit of its release 2.3.0), plus the directory assets from `.wordpress-org/` (`screenshot-1.png` now; add `banner-772x250.png`, `banner-1544x500.png`, `icon-128x128.png` and `icon-256x256.png` there for the listing).

To push a release that is already tagged to wordpress.org after this setup, re-run the release workflow for that tag in the Actions tab.

## Links in the plugin

`replyze/readme.txt` (the wordpress.org listing) and the plugin's settings page are part of the release ZIP, so their links change only with a plugin release. The UTM standard for them is `utm_source=wordpress&utm_medium=referral&utm_campaign=wordpress-live-chat&utm_content=<spot>`. Version 1.2.1 still sends `utm_source=wordpress&utm_medium=plugin&utm_content=settings` from the settings page (`signup_url()` in `replyze/replyze.php`), and `readme.txt` links have no UTMs. Switch both in the next plugin release.
