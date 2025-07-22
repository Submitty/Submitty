#!/usr/bin/env bash
set -ev

for cli_arg in "$@"
do
    if [[ $cli_arg =~ ^config=.* ]]; then
        SUBMITTY_CONFIG_DIR="$(readlink -f "$(echo "$cli_arg" | cut -f2 -d=)")"
    fi
done

if [ -z "${SUBMITTY_CONFIG_DIR}" ]; then
    echo "ERROR: This script requires a config dir argument"
    echo "Usage: ${BASH_SOURCE[0]} config=<config dir>"
    exit 1
fi

SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' "${SUBMITTY_CONFIG_DIR:?}/submitty.json")
# shellcheck disable=SC1091
source "${SUBMITTY_REPOSITORY:?}/.setup/install_submitty/get_globals.sh" "config=${SUBMITTY_CONFIG_DIR:?}"
################################################################################################################
################################################################################################################
# COMPILE AND INSTALL ANALYSIS TOOLS
# shellcheck disable=SC1091
source "${SUBMITTY_REPOSITORY:?}/.setup/bin/versions.sh"

echo -e "Compile and install analysis tools"

mkdir -p "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools"

pushd "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools"
# shellcheck disable=SC2154
if [[ ! -f VERSION || $(< VERSION) != "${AnalysisTools_Version}" ]]; then
    for b in count plagiarism diagnostics;
        do wget -nv "https://github.com/Submitty/AnalysisTools/releases/download/${AnalysisTools_Version}/${b}" -O ${b}
    done

    echo "${AnalysisTools_Version}" > VERSION
fi
popd > /dev/null

# change permissions
chown -R "${DAEMON_USER}:${COURSE_BUILDERS_GROUP}" "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools"
chmod -R 555                                       "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools"

# NOTE: These variables must match the same variables in install_system.sh
clangsrc="${SUBMITTY_INSTALL_DIR}/clang-llvm/src"

ANALYSIS_TOOLS_REPO="${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT/AnalysisTools"

# copying commonAST scripts
mkdir -p "${clangsrc}/llvm/tools/clang/tools/extra/ASTMatcher/"
mkdir -p "${clangsrc}/llvm/tools/clang/tools/extra/UnionTool/"

array=( astMatcher.py commonast.py unionToolRunner.py jsonDiff.py utils.py refMaps.py match.py eqTag.py context.py \
        removeTokens.py jsonDiffSubmittyRunner.py jsonDiffRunner.py jsonDiffRunnerRunner.py createAllJson.py )
for i in "${array[@]}"; do
    rsync -rtz "${ANALYSIS_TOOLS_REPO}/commonAST/${i}" "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools"
done

rsync -rtz "${ANALYSIS_TOOLS_REPO}/commonAST/unionTool.cpp"       "${clangsrc}/llvm/tools/clang/tools/extra/UnionTool/"
rsync -rtz "${ANALYSIS_TOOLS_REPO}/commonAST/CMakeLists.txt"      "${clangsrc}/llvm/tools/clang/tools/extra/ASTMatcher/"
rsync -rtz "${ANALYSIS_TOOLS_REPO}/commonAST/ASTMatcher.cpp"      "${clangsrc}/llvm/tools/clang/tools/extra/ASTMatcher/"
rsync -rtz "${ANALYSIS_TOOLS_REPO}/commonAST/CMakeListsUnion.txt" "${clangsrc}/llvm/tools/clang/tools/extra/UnionTool/CMakeLists.txt"

#copying tree visualization scrips
rsync -rtz "${ANALYSIS_TOOLS_REPO}/treeTool/make_tree_interactive.py" "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools"
rsync -rtz "${ANALYSIS_TOOLS_REPO}/treeTool/treeTemplate1.txt"        "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools"
rsync -rtz "${ANALYSIS_TOOLS_REPO}/treeTool/treeTemplate2.txt"        "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools"

#building commonAST excecutable
pushd "${ANALYSIS_TOOLS_REPO}"
g++ commonAST/parser.cpp commonAST/traversal.cpp -o "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools/commonASTCount.out"
g++ commonAST/parserUnion.cpp commonAST/traversalUnion.cpp -o "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools/unionCount.out"
popd > /dev/null

# FIXME: skipping this step as it has errors, and we don't use the output of it yet

# building clang ASTMatcher.cpp
# mkdir -p ${clanginstall}
# mkdir -p ${clangbuild}
# pushd ${clangbuild}
# TODO: this cmake only needs to be done the first time...  could optimize commands later if slow?
# cmake .
#ninja ASTMatcher UnionTool
# popd > /dev/null

# cp ${clangbuild}/bin/ASTMatcher ${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools/
# cp ${clangbuild}/bin/UnionTool ${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools/
# chmod o+rx ${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools/ASTMatcher
# chmod o+rx ${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools/UnionTool


# change permissions
chown -R "${DAEMON_USER}:${COURSE_BUILDERS_GROUP}" "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools"
chmod -R 555 "${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools"

################################################################################################################
################################################################################################################
# BUILD AND INSTALL ANALYSIS TOOLS TS

echo -e "Build and install analysis tools ts"

ANALYSIS_TOOLS_TS_REPO="${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT/AnalysisToolsTS/"

# # build project
/bin/bash "${ANALYSIS_TOOLS_TS_REPO}/install_analysistoolsts.sh"
