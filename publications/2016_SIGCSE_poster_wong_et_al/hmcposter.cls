% \iffalse meta-comment
%
% hmcposter.cls -- LaTeX class to create posters
% 
% Copyright (C) 2005-2012 Claire M. Connelly and 
% the Department of Mathematics, Harvey Mudd College
% 
% This program is free software; you can redistribute it and/or modify
% it under the terms of the GNU General Public License as published by
% the Free Software Foundation; either version 2 of the License, or
% (at your option) any later version.
% 
% This program is distributed in the hope that it will be useful,
% but WITHOUT ANY WARRANTY; without even the implied warranty of
% MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
% GNU General Public License for more details.
% 
% You should have received a copy of the GNU General Public License
% along with this program; if not, write to the Free Software
% Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
% 
% Contact: Claire Connelly <cmc@math.hmc.edu>
%          Department of Mathematics
%          Harvey Mudd College
%          1250 N. Dartmouth Ave.
%          Claremont, CA 91711
%
% $Id: hmcposter.cls 403 2012-08-21 20:10:23Z cmc $
% 
% \fi
%%
%% HMC Poster LaTeX class file.
%%

\NeedsTeXFormat{LaTeX2e}
\ProvidesClass{hmcposter}[2012/08/21 HMC Mathematics LaTeX poster class]

%%% Declare and set some options.

%% Set up ifs for Clinic and thesis.
\newif\if@clinic
\@clinicfalse
\newif\if@thesis
\@thesisfalse

%% Providing the clinic and thesis document-class options.
\DeclareOption{clinic}{\@clinictrue}
\DeclareOption{thesis}{\@thesistrue}

\DeclareOption*{\PassOptionsToClass{\CurrentOption}{article}}
%\ExecuteOptions{}
\ProcessOptions

\LoadClass{article}


%% Include local configuration file, if any.
\InputIfFileExists{hmcposter.cfg}{}{}

%% Set some dimensions for layout.
\setlength{\paperwidth}{67in}
\setlength{\paperheight}{36in}
\setlength{\textwidth}{63.5in}
\setlength{\textheight}{26.5in}
\setlength{\topmargin}{0.5in}
\setlength{\oddsidemargin}{0.75in}
\setlength{\headheight}{6in}
\setlength{\headsep}{-0.2in}
\setlength{\footskip}{1in}
\setlength{\voffset}{0pt}
\setlength{\hoffset}{0pt}

%% Require some packages.
\RequirePackage{ifthen}
\RequirePackage{mathpazo}
\RequirePackage[scaled=0.95]{helvet}
\usepackage{adjustbox}

\renewcommand{\ttdefault}{pcr}
\RequirePackage{sectsty}
\RequirePackage[font=small,font=sf,labelfont=bf]{caption}[2005/06/28]
\RequirePackage{graphics}
\RequirePackage{color}
\definecolor{hmcgold}{cmyk}{0,0.2,0.5,0.3}
\definecolor{black}{rgb}{0,0,0}
\definecolor{dkblue}{rgb}{0,0,0.47}
\definecolor{green}{rgb}{0,1,0}
\definecolor{white}{rgb}{0,0,0}
\RequirePackage{multicol}
\setlength{\columnsep}{1in}
\setlength{\columnseprule}{2pt}

%% Redefine the named font sizes so we can keep most of the code
%% intact whilst still having larger type.

\renewcommand{\LARGE}{\fontsize{72pt}{86pt}\selectfont}
\renewcommand{\Large}{\fontsize{64pt}{72pt}\selectfont}
\renewcommand{\large}{\fontsize{48pt}{60pt}\selectfont}
\renewcommand{\normalsize}{\fontsize{28pt}{44pt}\selectfont}
\renewcommand{\small}{\fontsize{30pt}{36pt}\selectfont}
\renewcommand{\footnotesize}{\fontsize{24pt}{30pt}\selectfont}

%%% Define some new fields for use in the header/title.

%% For a Clinic poster.
\if@clinic
\def\sponsor#1{\gdef\@sponsor{#1}}
\def\@sponsor{\@latex@warning@no@line{No \noexpand\sponsor given}}

\def\sponsorlogo#1{\gdef\@sponsorlogo{#1}}
\def\@sponsorlogo{\@latex@warning@no@line{No \noexpand\sponsorlogo given}}

\def\sponsorlogowidth#1{\gdef\@sponsorlogowidth{#1}}
\def\@sponsorlogowidth{2in}
\fi

%% For a thesis poster.
\if@thesis
\def\class#1{\gdef\@class{#1}}
\def\@class{\@latex@warning@no@line{No \noexpand\class given}}

\def\classlogo#1{\gdef\@classlogo{#1}}
\def\@classlogo{\@latex@warning@no@line{No \noexpand\classlogo given}}

\def\classlogowidth#1{\gdef\@classlogowidth{#1}}
\def\@classlogowidth{2in}
\fi

%% Shared Definitions
\def\department#1{\gdef\@department{#1}}
\def\@department{\@latex@warning@no@line{No \noexpand\department given}}
\def\@department{\@latex@warning@no@line{No \noexpand\department given}}

\def\posteryear#1{\gdef\@posteryear{#1}}
\def\@posteryear{\@latex@warning@no@line{No \noexpand\posteryear given}}

\providecommand{\copyrightholder}[1]{%
  \gdef\@copyrightholder{#1}}


%%% Set up the header.

%%\newlength{\ignored@length}
%%\newcommand{\set@ess}[1]{%
  %%\renewcommand{\and}{%
    %%\global\def\@ess{s}%
  %%}%
  %%\global\def\@ess{}%
  %%\settowidth{\ignored@length}{#1}
%%}
%%\newcommand{\@andcommaspace}{, }


%% Define a command to include the HMC Logo.
\DeclareRobustCommand{\@hmclogo}{%
  \begin{minipage}{9.5in}
     \raisebox{1.0in}{\resizebox{14.5in}{!}{\includegraphics{lgplogo2cm}}}
  \end{minipage}
}

%% Define a command to include the RPI Logo.
\DeclareRobustCommand{\@rpilogo}{%
  \begin{minipage}{17.0in}
    \raisebox{1.0in}{\resizebox{5.2in}{!}{\includegraphics{logo_cs_design_1}}}
    \hspace{1.0in}
    \raisebox{2.7in}{\resizebox{10.0in}{!}{\includegraphics{RCOS_NEW_LOGO}}}
  \end{minipage}
}


%%% Set up the header for the poster.

%% Use fancyhdr.
\RequirePackage{fancyhdr}

%% Lay out the header
\pagestyle{fancy}
\fancyhf{}                      % Clear all fields.

%% For Clinic
\if@clinic
\fancyhead[L]{\raisebox{2in}{\@hmclogo}}

\ifthenelse{\isundefined\@sponsorlogo}{}{%
  \fancyhead[R]{\resizebox{!}{\@sponsorlogowidth}{%
      \includegraphics{\@sponsorlogo}}}}
\fi

%% For thesis.
\if@thesis
    \fancyhead[L]{\raisebox{5.5in}{\@hmclogo}}
    \fancyhead[R]{\raisebox{5.5in}{\@rpilogo}}
\fi


%% For Clinic
\if@clinic
\fancyhead[C]{%
  \begin{center}
    \color{black}\Large\textsf{\@posteryear\ \@sponsor\ \@department\ Clinic}\\%
    \color{hmcgold}\fontsize{100pt}{120pt}\selectfont\textsf{\textbf{\@title}}
  \end{center}
}
  \fi

%% For thesis
\if@thesis
\fancyhead[C]{%
%  \begin{center}
%
%
% ..
%\adjustbox{varwidth=<max width>,raise=<amount>}{ multi \\ line \\ text }
% or:
%
 %  \raisebox{3.5in}{
    \adjustbox{varwidth=30in,raise=5.5in}{
    \begin{center}
    \color{dkblue}\fontsize{80pt}{100pt}\selectfont\textsf{\textbf{\@title}} \\
\vspace{0.2in}
    \color{black}\fontsize{45pt}{50pt}\textsf{\@author}
    \end{center}
    }
%
%    \raisebox{19.5in}{
%
%
}
%    \color{black}\fontsize{45pt}{50pt}\textsf{\@author}\\[-11.5em]}
%    \color{dkblue}\fontsize{80pt}{100pt}\selectfont\textsf{\textbf{\@title}}\\[2.80em]}
%  \end{center}
%
%\fi
%\if@thesis
%\fancyhead[C]{%
%  \begin{center}
%    {\color{black}\Large\textsf{\@class}\\}%
%    {\color{dkblue}\fontsize{80pt}{100pt}\selectfont\textsf{\textbf{\@title}}\\[2.80em]}
%    {\color{black}\large\textsf{\@author}\\[-0.5em]}
%  \end{center}
%}
\fi

%% Define the footer.
\fancyfoot[R]{%
  \ifthenelse{\isundefined{\@copyrightholder}}%
  {}% Do nothing.
  {\textcolor{black}{Copyright \copyright{}~\@posteryear\
      \@copyrightholder}}
}
\fancyfoot[L]{%
  \ifthenelse{\isundefined{\@advisor}}%
  {}% Do nothing.
  {\textcolor{black}{%
      \set@ess{\@advisor}%
      \textbf{Advisor\@ess:}
      \global\let\and\@andcommaspace
      \@advisor%
    }
  }\quad%
  \ifthenelse{\isundefined{\@reader}}%
  {}% Do nothing.
  {\textcolor{black}{%
      \set@ess{\@reader}%
      \textbf{Reader\@ess:}
      \global\let\and\@andcommaspace
      \@reader%
    }
  }
}
\renewcommand{\headrulewidth}{0pt}
\renewcommand{\footrulewidth}{0pt}



%%% Colorize header and footer rules.
%%%
%%% This code taken from fancyhdr.sty; written by Piet van Oostrum.

\newcommand{\@rulecolor}{white}

\if@thesis
\renewcommand{\@rulecolor}{white}
\fi

%%\renewcommand{\footrule}{{\color{\@rulecolor}\if@fancyplain\let\footrulewidth\plainfootrulewidth\fi
    %%\vskip-\footruleskip\vskip-\footrulewidth
    %%\hrule\@width\headwidth\@height\footrulewidth\vskip\footruleskip}}
%%\renewcommand{\headrule}{{\color{\@rulecolor}\if@fancyplain\let\headrulewidth\plainheadrulewidth\fi
    %%\hrule\@height\headrulewidth\@width\headwidth \vskip-\headrulewidth}}
%%% End code from fancyhdr.sty.


%%% Set color of vertical rules.
%%%
%%% This code is modified from the code in multicol.sty; written by
%%% Frank Mittelbach and part of standard LaTeX.
%%%
%%% It will probably break if the original routine is modified in
%%% future versions of multicol.sty, so be sure to check there.

\renewcommand{\page@sofar}{%
   \process@cols\mult@rightbox
       {\ifvoid\count@
          \setbox\count@\hbox to\hsize{}%
        \else
          \wd\count@\hsize
        \fi}%
   \mult@info\z@
    {Column spec:\MessageBreak
       (\the\multicol@leftmargin\space -->
        \the\full@width\space = \the\hsize
        \space x \the\col@number)%
     }%
\ifvmode\else\errmessage{Multicol Error}\fi
   \moveright\multicol@leftmargin
    \hbox to\full@width{%
     \process@cols\mult@gfirstbox{\box\count@
       \hss{\color{white}\vrule\@width\columnseprule}\hss}%
     \box\mult@rightbox
      \rlap{\phantom p}%
    }%
}
%%% End code from multicol.sty.

\setlength{\multicolsep}{2em}

\newenvironment{poster}{%
\thispagestyle{fancy}
%{\color{hmcgold}\rule{\textwidth}{2pt}}
\begin{multicols*}{5}
}%
{\end{multicols*}
}

\renewenvironment{figure}
               {\def\@captype {figure}}
               {}

\renewenvironment{table}
               {\def\@captype {table}}
               {}


%%% Make section headers sans-serif.

\allsectionsfont{\sffamily}

%%% Suppress section numbering.
\renewcommand{\@seccntformat}[1]{\relax}

\endinput
