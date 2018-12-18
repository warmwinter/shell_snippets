#!/bin/bash

# Get your API key from https://ram.console.aliyun.com/#/user/list (RECOMMAND)
# OR from https://usercenter.console.aliyun.com/#/manage/ak (NOT RECOMMAND)

# Constant
ACCESSKEYID=''
ACCESSKEYSECRTE=''
ALIDNSURI='https://alidns.aliyuncs.com/'

# declare -A  定义关联数组 类似字典 键值对
# Common parameters https://help.aliyun.com/document_detail/29745.html
declare -A PARAMS
PARAMS=( \
	[Format]="json" \
	[Version]="2015-01-09" \
	[AccessKeyId]=$ACCESSKEYID \
	[SignatureMethod]="HMAC-SHA1" \
	[Timestamp]=`date -u +%Y-%m-%dT%H:%M:%SZ` \
	[SignatureVersion]="1.0" \
	[SignatureNonce]=`date +%s%N | md5sum |cut -c 1-9` \
)

# get Domain
function getDomain()
{
	if [[ -z $1 ]]; then
		echo 'Domain empty'
		exit 1
	fi

	# Strip only the top domain to get the zone id
	DOMAIN=$(expr match "$1" '.*\.\(.*\..*\)')

	echo $DOMAIN
}

# get DNS list for the special domain
# https://help.aliyun.com/document_detail/29776.html
function getDNSList()
{
	if [[ -z $1 ]]; then
		echo 'Domain empty'
		exit 1
	fi

	declare -A TMP_PARAMS
	TMP_PARAMS=( \
		[Action]="DescribeDomainRecords" \
		[DomainName]=$(getDomain $1) \
		[PageNumber]=1 \
		[PageSize]=100 \
		[RRKeyWord]="%_acme-challenge%" \
		# https://help.aliyun.com/document_detail/29805.html
		[TypeKeyWord]="TXT" \
	)

	declare -A MERGE_PARAMS
	#MERGE_PARAMS=PARAMS

	MERGE_PARAMS["tttt"]=888 #$TMP_PARAMS
	echo ${!MERGE_PARAMS[*]}
}

getDNSList www.miss77.net

# Signature method https://help.aliyun.com/document_detail/29747.html
# Signature

function genSinature()
{
	declare -A SIGN_PARAMS
	SIGN_PARAMS=$1
	echo ${!SIGN_PARAMS[*]}
}

genSinature $PARAMS

# 打印所有key
echo ${!PARAMS[*]} | tr -t [" "] ["\n"] | sort | tr -t ["\n"] [" "]
printf "\n"
    
# 打印所有value @ 和 * 同样的意思
# echo ${PARAMS[@]}
