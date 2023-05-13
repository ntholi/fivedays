import Layout from '@/components/layout/Layout';
import googleClassroom from '@/lib/helpers/googleClassroom';
import { classroom_v1 } from 'googleapis';
import { GetServerSideProps } from 'next';
import { getSession } from 'next-auth/react';
import { useRouter } from 'next/router';
import React from 'react';

type Props = {
  assessments: classroom_v1.Schema$CourseWork[];
};

export default function ClassPage({ assessments }: Props) {
  const router = useRouter();

  return (
    <Layout>
      <h1>{router.query.name}</h1>
      <h2>Assessments</h2>
      <hr />
      {assessments.map((it) => (
        <div key={it.id}>{it.title}</div>
      ))}
    </Layout>
  );
}

export const getServerSideProps: GetServerSideProps = async (context) => {
  const session = await getSession(context);
  const classroom = googleClassroom(session);

  try {
    const res = await classroom.courses.courseWork.list({
      courseId: context.params?.id as string,
    });

    return {
      props: {
        assessments: res.data.courseWork || [],
      },
    };
  } catch (err) {
    console.error('Error: ', err);
    return {
      props: {
        classes: [],
      },
    };
  }
};
