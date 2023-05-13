import Layout from '@/components/layout/Layout';
import googleClassroom from '@/lib/helpers/googleClassroom';
import { classroom_v1, google } from 'googleapis';
import { GetServerSideProps } from 'next';
import { getSession, useSession } from 'next-auth/react';
import { FC } from 'react';

interface ClassesPageProps {
  classes: classroom_v1.Schema$Course[];
}

const ClassesPage: FC<ClassesPageProps> = ({ classes }) => {
  const { data: session } = useSession();

  if (!session) {
    return (
      <div>
        <h1>Please sign in</h1>
      </div>
    );
  }

  return (
    <Layout>
      <h1>Your Classes</h1>

      {classes.map((course) => (
        <div key={course.id}>
          <h2>{course.name}</h2>
          <p>{course.description}</p>
        </div>
      ))}
    </Layout>
  );
};

export const getServerSideProps: GetServerSideProps = async (context) => {
  const session = await getSession(context);

  if (!session) {
    return {
      props: {
        classes: ['Login'],
      },
    };
  }

  const classroom = googleClassroom(session);

  try {
    const response = await classroom.courses.list();

    // The list of classes will be in response.data.courses
    const classes = response.data.courses || [];

    return {
      props: {
        classes,
      },
    };
  } catch (err) {
    // Handle error
    console.error('Error: ', err);

    return {
      props: {
        classes: [],
      },
    };
  }
};

export default ClassesPage;
