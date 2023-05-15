import AssessmentCard from '@/components/classes/AssessmentCard';
import Layout from '@/components/layout/Layout';
import googleClassroom from '@/lib/helpers/googleClassroom';
import {
  Text,
  Container,
  Divider,
  Accordion,
  Title,
  createStyles,
  rem,
  Group,
  Paper,
} from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import { GetServerSideProps, NextPage } from 'next';
import { getSession } from 'next-auth/react';
import { useRouter } from 'next/router';
import React from 'react';

type Props = {
  studentSubmissions: {
    id: string;
    userId: string;
    attachments: classroom_v1.Schema$Attachment[];
  }[];
};

const GradePage: NextPage<Props> = ({ studentSubmissions }) => {
  return (
    <>
      {studentSubmissions.map((submission) => (
        <div key={submission.id}>
          {submission.attachments[0]?.driveFile?.alternateLink ?? null}
        </div>
      ))}
    </>
  );
};

export const getServerSideProps: GetServerSideProps = async (context) => {
  const session = await getSession(context);
  const classroom = googleClassroom(session);

  const { courseId } = context.query;

  const res = await classroom.courses.courseWork.studentSubmissions.list({
    courseWorkId: context.params?.id as string,
    courseId: courseId as string,
  });

  const studentSubmissions = res.data.studentSubmissions?.map((submission) => {
    return {
      id: submission.id,
      userId: submission.userId,
      attachments: submission.assignmentSubmission?.attachments || [],
      // Map more properties as needed
    };
  });

  return { props: { studentSubmissions: studentSubmissions || [] } };
};

export default GradePage;
