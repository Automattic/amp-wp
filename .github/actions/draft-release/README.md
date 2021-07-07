# Draft release action

This action drafts a release for the specified milestone and release branch.

## Inputs

### `milestone`

**Required** Milestone name.

## Outputs

### `asset_upload_url`

The URL for uploading assets to the release.

## Example usage

```yaml
uses: ./.github/actions/draft-release
env:
  GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
with:
  milestone: v3.2.1
```
