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

BEFORE_HASH='94ec375' # `$HASHCMD`
$GIT pull --ff-only -q
AFTER_HASH=`$HASHCMD`

if [ "$BEFORE_HASH" = "$AFTER_HASH" ]; then
    echo "目前还没有可更新的文件，请稍后再试"
    exit 0
fi
# Select only files that are Added (A), Copied (C), Deleted (D),
# Modified (M), Renamed (R), have their type (i.e. regular file, symlink, submodule, …​) changed (T), 
# are Unmerged (U), are Unknown (X), or have had their pairing Broken (B). 
SYNC_ITEMS=`$GIT diff --name-only --diff-filter=ACMRT $BEFORE_HASH $AFTER_HASH` # upload/

echo '' > $LIST_FILE
for item in $SYNC_ITEMS; do
    PREFIX=${item:0:7}
    if [ "$PREFIX" != "upload/" ]; then
        continue
    fi

    # echo "+ "${item:6} >> $LIST_FILE
    echo ${item:7} >> $LIST_FILE
done
#echo '- *' >> $LIST_FILE
#echo '- /install/index.php' >> $LIST_FILE
#echo '- /install/update.php' >> $LIST_FILE
#echo '- /uc_server/install/index.php' >> $LIST_FILE
#echo '- /uc_server/install/update.php' >> $LIST_FILE

cat $LIST_FILE

# 同步
rsync --files-from=$LIST_FILE --list-only -rltD --no-p --no-g --no-o -vPhu ${SOURCE}/upload/ ${DEST}/

rm -rf $LIST_FILE
