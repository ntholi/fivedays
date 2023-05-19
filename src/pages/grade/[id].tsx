import { useEffect, useState } from 'react';
import {
  AppShell as Shell,
  Navbar,
  Footer,
  Aside,
  Text,
  MediaQuery,
  Burger,
  useMantineTheme,
  Paper,
} from '@mantine/core';
import Header from '@/components/layout/Header';
import { useRouter } from 'next/router';
import { classroom_v1 } from 'googleapis';
import { axiosInstance } from '@/lib/config/axios';
import { useSession } from 'next-auth/react';
import StudentList from '@/components/grade/StudentList';

type Props = {
  children: React.ReactNode;
};

export default function GraderPage({ children }: Props) {
  const [openNav, setOpenNav] = useState(false);
  const theme = useMantineTheme();
  const router = useRouter();
  const { id: courseWorkId, courseId } = router.query;
  const { data: session } = useSession();
  const [submissions, setSubmissions] = useState<StudentSubmission[]>([]);
  const [active, setActive] = useState<StudentSubmission | null>(null);

  useEffect(() => {
    async function getData() {
      const submitPromise = axiosInstance(session)?.get(
        `/courses/${courseId}/courseWork/${courseWorkId}/studentSubmissions`
      );
      const studentPromise = axiosInstance(session)?.get(
        `/courses/${courseId}/students`
      );
      const [submitRes, studentRes] = await Promise.all([
        submitPromise,
        studentPromise,
      ]);
      const data = mapStudentSubmission(
        submitRes.data.studentSubmissions,
        studentRes.data.students
      );
      setSubmissions(data);
    }
    if (session) {
      getData();
    }
  }, [session]);

  return (
    <Shell
      styles={{
        main: {
          background:
            theme.colorScheme === 'dark'
              ? theme.colors.dark[8]
              : theme.colors.gray[0],
        },
      }}
      navbarOffsetBreakpoint="sm"
      asideOffsetBreakpoint="sm"
      navbar={
        <Navbar
          p="md"
          hiddenBreakpoint="sm"
          hidden={!openNav}
          width={{ sm: 300 }}
        >
          <StudentList
            submissions={submissions}
            setActive={setActive}
            active={active}
          />
        </Navbar>
      }
      aside={
        <Aside hiddenBreakpoint="sm" width={{ sm: 200, lg: 300 }}>
          <Text>Aside</Text>
        </Aside>
      }
      header={<Header />}
    >
      <Paper>
        {courseWorkId}
        {courseId}
      </Paper>
    </Shell>
  );
}

function mapStudentSubmission(
  submissions: classroom_v1.Schema$StudentSubmission[],
  students: classroom_v1.Schema$Student[]
): StudentSubmission[] {
  const data = submissions.map((it: any) => {
    const submission: StudentSubmission = {
      id: it.id,
      userId: it.userId,
      studentName: it.userId,
      courseWorkId: it.courseWorkId,
      alternateLink: it.alternateLink,
      draftGrade: it.draftGrade,
      late: it.late,
      updateTime: it.updateTime,
    };
    let attachments: SubmissionAttachment[] = [];
    for (const item of it.assignmentSubmission.attachments) {
      const attachment: SubmissionAttachment = {
        type: 'driveFile',
        title: '',
        url: '',
      };
      if (item.driveFile) {
        attachment.type = 'driveFile';
        attachment.id = item.driveFile.id;
        attachment.title = item.driveFile.title;
        attachment.url = item.driveFile.alternateLink;
      } else if (item.link) {
        attachment.type = 'link';
        attachment.title = item.link.title;
        attachment.url = item.link.url;
      }
      attachments.push(attachment);
    }
    submission.attachments = attachments;
    return submission;
  });

  for (const item of data) {
    const student = students.find((it) => it.userId === item.userId);
    if (student) {
      item.studentName = student.profile?.name?.fullName || '';
    }
  }

  return data;
}
