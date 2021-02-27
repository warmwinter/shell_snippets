#!/bin/sh

# Check if user is root
if [ $(id -u) -ne "0" ]; then
    printf "Error: You must be root to run this script!\n"
    exit 1
fi

GIT='git --no-pager'
HASHCMD=$GIT' log -1 --pretty=format:%h'
SOURCE='/web/wwwroot/discuz/DiscuzX'
DEST='/web/wwwroot/discuz/site'
LIST_FILE='/tmp/git_diff.list'

# 检查源目录是否存在
if [ ! -d ${SOURCE} ]; then
    echo "源目录 "${SOURCE}" 不存在"
    exit 1
fi

# 检查目标目录是否存在
if [ ! -d ${DEST} ]; then
    echo "目标目录 "${DEST}" 不存在"
    exit 1
fi

# 简单检查源目录是否是 git 目录
if [ ! -d ${SOURCE}'/.git' ]; then
    echo "源目录 "${SOURCE}" 不是有效的 GIT 仓库"
    exit 1
fi

# GITCMD=$GIT' --git-dir='$SOURCE

# 切换到源目录
cd $SOURCE

if [ ! -z "$1" ]; then
    BEFORE_HASH=${1:0:7}
else
    BEFORE_HASH=`$HASHCMD`
fi
$GIT pull --ff-only -q
AFTER_HASH=`$HASHCMD`

if [ "$BEFORE_HASH" = "$AFTER_HASH" ]; then
    echo "目前还没有可更新的文件，请稍后再试"
    exit 0
fi

echo "差异："$BEFORE_HASH" ... "$AFTER_HASH

# Select only files that are Added (A), Copied (C), Deleted (D),
# Modified (M), Renamed (R), have their type (i.e. regular file, symlink, submodule, …​) changed (T), 
# are Unmerged (U), are Unknown (X), or have had their pairing Broken (B). 
SYNC_ITEMS=`$GIT diff --name-only --diff-filter=ACMRT $BEFORE_HASH $AFTER_HASH` # upload/

# ${#array[@]} 
SYNC_ARRAY=($SYNC_ITEMS)
echo "差异文件数："${#SYNC_ARRAY[@]}

echo '' > $LIST_FILE
for item in $SYNC_ITEMS; do
    PREFIX=${item:0:7}
    if [ "$PREFIX" != "upload/" ]; then
        continue
    fi

    FILE_FULL=${item:7}

    if [ "$FILE_FULL" = "install/index.php" ]; then
        continue
    fi

    if [ "$FILE_FULL" = "install/update.php" ]; then
        continue
    fi

    if [ "$FILE_FULL" = "uc_server/install/index.php" ]; then
        continue
    fi

    if [ "$FILE_FULL" = "uc_server/install/update.php" ]; then
        continue
    fi

    # echo "+ "${item:6} >> $LIST_FILE
    echo "$FILE_FULL" >> $LIST_FILE
done

cat $LIST_FILE

# 同步
# -a = -rlptgoD
rsync --files-from=$LIST_FILE -rltD --no-p --no-g --no-o -R -vPhu ${SOURCE}/upload/ ${DEST}/

rm -rf $LIST_FILE

echo "同步完成"

exit 0
