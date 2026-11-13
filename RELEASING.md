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
   - deploys to wordpress.org, only when the SVN secrets below are set. Without them that job prints a notice and does nothing.

The README's Quickstart links to `releases/latest`, so it points to the new ZIP as soon as the release exists.

## Build the ZIP by hand

```bash
zip -r -X replyze.zip replyze -x '*.DS_Store'
```

## wordpress.org (one-time setup)

The slug `replyze` was free on 2026-11-13. Until the plugin is approved, the SVN job stays skipped.

1. Create a wordpress.org account for the company. Its username must be the one in `Contributors:` in `replyze/readme.txt` (now `replyze`); change that line if the account gets another name.
2. Submit the plugin at https://wordpress.org/plugins/developers/add/ with the `replyze.zip` of the latest release. Review takes days to weeks; answer the review emails from that account.
3. After approval, the plugin gets an SVN repository at `https://plugins.svn.wordpress.org/replyze/`. In this GitHub repository, add two Actions secrets (Settings → Secrets and variables → Actions):
   - `SVN_USERNAME`: the wordpress.org username
   - `SVN_PASSWORD`: the SVN password from https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password
4. The next tag deploys the `replyze/` folder to SVN `trunk` and `tags/X.Y.Z` (with `10up/action-wordpress-plugin-deploy`), plus the directory assets from `.wordpress-org/` (`screenshot-1.png` now; add `banner-772x250.png`, `banner-1544x500.png`, `icon-128x128.png` and `icon-256x256.png` there for the listing).

To push a release that is already tagged to wordpress.org after adding the secrets, re-run the release workflow for that tag in the Actions tab.
